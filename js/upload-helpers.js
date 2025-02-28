/**
 * Upload helper functions for FreeNetly
 */

// Constants for upload configuration
const UPLOAD_CONSTANTS = {
    MIN_CHUNK_SIZE: 1 * 1024 * 1024, // 1MB
    MAX_CHUNK_SIZE: 10 * 1024 * 1024, // 10MB
    MAX_RETRIES: 3,
    RETRY_DELAY: 1000, // Base delay in ms
    MAX_FILE_SIZE: 2 * 1024 * 1024 * 1024, // 2GB
};

/**
 * Determines optimal chunk size based on file size
 * @param {Number} fileSize - Size of file in bytes
 * @returns {Number} - Optimal chunk size in bytes
 */
function getOptimalChunkSize(fileSize) {
    if (fileSize > 500 * 1024 * 1024) {
        return 10 * 1024 * 1024; // 10MB for files > 500MB
    } else if (fileSize > 100 * 1024 * 1024) {
        return 5 * 1024 * 1024; // 5MB for files > 100MB
    } else if (fileSize > 10 * 1024 * 1024) {
        return 2 * 1024 * 1024; // 2MB for files > 10MB
    } else {
        return 1 * 1024 * 1024; // 1MB for smaller files
    }
}

/**
 * Formats file size to human-readable string
 * @param {Number} bytes - Size in bytes
 * @returns {String} - Formatted size string
 */
function formatFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + ' bytes';
    } else if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(1) + ' KB';
    } else if (bytes < 1024 * 1024 * 1024) {
        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    } else {
        return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
    }
}

/**
 * Formats upload speed to human-readable string
 * @param {Number} bytesPerSecond - Upload speed in bytes per second
 * @returns {String} - Formatted speed string
 */
function formatSpeed(bytesPerSecond) {
    if (bytesPerSecond < 1024) {
        return `${bytesPerSecond.toFixed(1)} B/s`;
    } else if (bytesPerSecond < 1024 * 1024) {
        return `${(bytesPerSecond / 1024).toFixed(1)} KB/s`;
    } else {
        return `${(bytesPerSecond / 1024 / 1024).toFixed(2)} MB/s`;
    }
}

/**
 * Formats time remaining to human-readable string
 * @param {Number} seconds - Time remaining in seconds
 * @returns {String} - Formatted time string
 */
function formatTimeRemaining(seconds) {
    if (seconds < 60) {
        return `${seconds} seconds remaining`;
    } else if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} minutes ${seconds % 60} seconds remaining`;
    } else {
        return `${Math.floor(seconds / 3600)} hours ${Math.floor((seconds % 3600) / 60)} minutes remaining`;
    }
}

/**
 * Uploads a chunk of a file with retry logic
 * @param {File} file - The file being uploaded
 * @param {Number} chunkIndex - Current chunk index
 * @param {Number} chunkSize - Size of each chunk
 * @param {Number} totalChunks - Total number of chunks
 * @param {String} tempId - Temporary ID for this upload
 * @returns {Promise} - Promise that resolves on successful chunk upload
 */
async function uploadChunkWithRetry(file, chunkIndex, chunkSize, totalChunks, tempId) {
    const start = chunkIndex * chunkSize;
    const end = Math.min(start + chunkSize, file.size);
    const chunk = file.slice(start, end);
    
    const url = `index.php?action=upload_chunk&chunk=${chunkIndex}&chunks=${totalChunks}` + 
              `&name=${encodeURIComponent(file.name)}&size=${file.size}` + 
              `&type=${encodeURIComponent(file.type)}&temp_id=${tempId}`;
    
    let retries = 0;
    
    while (retries < UPLOAD_CONSTANTS.MAX_RETRIES) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: chunk,
                headers: {
                    'Content-Type': 'application/octet-stream'
                }
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error ${response.status}: ${errorText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                return data;
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        } catch (error) {
            retries++;
            console.error(`Chunk ${chunkIndex} upload failed (attempt ${retries}/${UPLOAD_CONSTANTS.MAX_RETRIES}):`, error);
            
            if (retries >= UPLOAD_CONSTANTS.MAX_RETRIES) {
                throw error;
            }
            
            // Exponential backoff
            const delay = UPLOAD_CONSTANTS.RETRY_DELAY * Math.pow(2, retries - 1);
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
}

/**
 * Verifies that all chunks were uploaded successfully
 * @param {File} file - The file being uploaded
 * @param {String} tempId - Temporary ID for this upload
 * @returns {Promise} - Promise that resolves with verification result
 */
async function verifyUpload(file, tempId) {
    const url = `index.php?action=upload_chunk&verify=true` + 
              `&name=${encodeURIComponent(file.name)}&size=${file.size}` + 
              `&type=${encodeURIComponent(file.type)}&temp_id=${tempId}`;
    
    const response = await fetch(url);
    
    if (!response.ok) {
        throw new Error(`HTTP error ${response.status}`);
    }
    
    return await response.json();
}

// Export functions
window.UploadHelpers = {
    getOptimalChunkSize,
    formatFileSize,
    formatSpeed,
    formatTimeRemaining,
    uploadChunkWithRetry,
    verifyUpload,
    UPLOAD_CONSTANTS
};
