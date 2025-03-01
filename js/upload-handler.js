/**
 * Enhanced file upload handler with error handling and retries
 */
class FileUploadHandler {
    constructor(options = {}) {
        this.options = {
            chunkSize: 2 * 1024 * 1024, // 2MB default chunk size
            maxRetries: 3,              // Default retry count
            retryDelay: 1000,           // Base delay between retries (ms)
            ...options
        };
        
        this.uploadInProgress = false;
        this.currentProgress = 0;
        this.chunkStats = null;
        this.abortController = null;
    }
    
    /**
     * Upload a file with appropriate method based on size
     * @param {File} file - The file to upload
     * @param {Function} progressCallback - Callback for progress updates
     * @param {Function} chunkCallback - Callback for chunk progress
     * @returns {Promise<Object>} - Response data with download link
     */
    async uploadFile(file, progressCallback, chunkCallback) {
        if (this.uploadInProgress) {
            throw new Error('Upload already in progress');
        }
        
        // Validate file
        if (!file) {
            throw new Error('No file provided');
        }
        
        // Check file size (2GB limit)
        const maxSize = 2 * 1024 * 1024 * 1024;
        if (file.size > maxSize) {
            throw new Error('File is too large. Maximum file size is 2 GB.');
        }
        
        try {
            this.uploadInProgress = true;
            this.currentProgress = 0;
            
            // Update progress
            if (progressCallback) {
                progressCallback(0);
            }
            
            // Choose upload method based on file size
            let response;
            if (file.size < 10 * 1024 * 1024) {
                // Small file: direct upload
                response = await this._regularUpload(file, progressCallback);
            } else {
                // Large file: chunked upload
                response = await this._chunkedUpload(file, progressCallback, chunkCallback);
            }
            
            return response;
        } finally {
            this.uploadInProgress = false;
            this.chunkStats = null;
        }
    }
    
    /**
     * Cancel any in-progress upload
     */
    cancelUpload() {
        if (this.abortController) {
            this.abortController.abort();
            this.uploadInProgress = false;
            this.currentProgress = 0;
        }
    }
    
    /**
     * Regular upload for small files
     * @private
     */
    async _regularUpload(file, progressCallback) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', file);
            
            const xhr = new XMLHttpRequest();
            
            // Setup progress tracking
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const progress = Math.round((e.loaded * 100) / e.total);
                    this.currentProgress = progress;
                    if (progressCallback) progressCallback(progress);
                }
            });
            
            // Handle completion
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (!response.success) {
                            reject(new Error(response.error || 'Upload failed'));
                        } else {
                            resolve(response);
                        }
                    } catch (e) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        reject(new Error(errorData.error || `Upload failed with status: ${xhr.status}`));
                    } catch (e) {
                        reject(new Error(`Upload failed with status: ${xhr.status}`));
                    }
                }
            };
            
            xhr.onerror = () => reject(new Error('Network error during upload'));
            xhr.onabort = () => reject(new Error('Upload was aborted'));
            
            xhr.open('POST', 'index.php', true);
            xhr.send(formData);
            
            // Store reference for potential cancellation
            this.abortController = {
                abort: () => xhr.abort()
            };
        });
    }
    
    /**
     * Chunked upload for large files
     * @private
     */
    async _chunkedUpload(file, progressCallback, chunkCallback) {
        try {
            // Generate a unique ID for this file upload session
            const fileId = Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
            
            // Calculate total chunks
            const totalChunks = Math.ceil(file.size / this.options.chunkSize);
            let totalUploaded = 0;
            
            // Initialize upload session
            const initResponse = await this._fetchWithTimeout('chunk_upload.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'init',
                    fileId: fileId,
                    fileName: file.name,
                    totalChunks: totalChunks,
                    fileSize: file.size
                }),
                timeout: 10000 // 10 second timeout
            });
            
            const initData = await initResponse.json();
            if (!initData.success) {
                throw new Error(initData.error || 'Failed to initialize upload');
            }
            
            // Upload each chunk with retry logic
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * this.options.chunkSize;
                const end = Math.min(file.size, start + this.options.chunkSize);
                const chunk = file.slice(start, end);
                
                // Update chunk stats through callback
                const currentChunkStats = {
                    current: chunkIndex + 1,
                    total: totalChunks
                };
                this.chunkStats = currentChunkStats;
                
                if (chunkCallback) {
                    chunkCallback(currentChunkStats);
                }
                
                // Upload chunk with retries
                await this._uploadChunkWithRetries(
                    chunk, 
                    fileId, 
                    chunkIndex, 
                    file.name, 
                    totalChunks
                );
                
                // Update progress
                totalUploaded += chunk.size;
                const progress = Math.round((totalUploaded * 100) / file.size);
                this.currentProgress = progress;
                
                if (progressCallback) {
                    progressCallback(progress);
                }
            }
            
            // Finalize upload
            const finalFormData = new FormData();
            finalFormData.append('fileId', fileId);
            finalFormData.append('fileName', file.name);
            finalFormData.append('totalSize', file.size);
            finalFormData.append('chunksComplete', 'true');
            
            const finalResponse = await this._fetchWithTimeout('index.php', {
                method: 'POST',
                body: finalFormData,
                timeout: 30000 // 30 second timeout for finalization
            });
            
            const result = await finalResponse.json();
            if (!result.success) {
                throw new Error(result.error || 'Failed to finalize upload');
            }
            
            return result;
        } catch (error) {
            // Enhance error message for common issues
            if (error.message.includes('insufficient_space')) {
                throw new Error('Not enough storage space available. Please try a smaller file or contact support.');
            }
            throw error;
        }
    }
    
    /**
     * Upload a single chunk with retries
     * @private
     */
    async _uploadChunkWithRetries(chunk, fileId, chunkIndex, fileName, totalChunks) {
        let attempts = 0;
        
        while (attempts < this.options.maxRetries) {
            attempts++;
            try {
                // Create form data for this chunk
                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('fileId', fileId);
                formData.append('chunkIndex', chunkIndex);
                formData.append('fileName', fileName);
                formData.append('totalChunks', totalChunks);
                
                // Upload the chunk
                const response = await this._fetchWithTimeout('chunk_upload.php', {
                    method: 'POST',
                    body: formData,
                    timeout: 30000 // 30 second timeout per chunk
                });
                
                const responseData = await response.json();
                if (!responseData.success) {
                    throw new Error(responseData.error || `Failed to upload chunk ${chunkIndex + 1}`);
                }
                
                return responseData;
            } catch (error) {
                // Last attempt failed, propagate the error
                if (attempts >= this.options.maxRetries) {
                    throw error;
                }
                
                // Wait before retrying (exponential backoff)
                const delay = this.options.retryDelay * Math.pow(2, attempts - 1);
                await new Promise(resolve => setTimeout(resolve, delay));
                console.warn(`Retrying chunk ${chunkIndex + 1} (attempt ${attempts + 1})`);
            }
        }
    }
    
    /**
     * Helper method to add timeout to fetch requests
     * @private
     */
    async _fetchWithTimeout(url, options = {}) {
        const { timeout = 8000, ...fetchOptions } = options;
        
        this.abortController = new AbortController();
        const signal = this.abortController.signal;
        
        // Set up the timeout
        const timeoutId = setTimeout(() => {
            this.abortController.abort();
        }, timeout);
        
        try {
            const response = await fetch(url, { 
                ...fetchOptions, 
                signal 
            });
            
            // Check for HTTP errors
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error || `HTTP error! Status: ${response.status}`);
            }
            
            return response;
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error(`Request to ${url} timed out after ${timeout}ms`);
            }
            throw error;
        } finally {
            clearTimeout(timeoutId);
        }
    }
}

// Export for use in browser and Node.js if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FileUploadHandler;
}
