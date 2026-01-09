function reportFile(fileId, fileName) {
    Swal.fire({
        title: 'Report File',
        html: `
            <p class="mb-2">Report "${fileName}"</p>
            <textarea id="reportReason" 
                      class="w-full p-2 border rounded"
                      placeholder="Please describe the reason for reporting this file"
                      rows="4"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: 'Submit Report',
        confirmButtonColor: '#EF4444',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const reason = document.getElementById('reportReason').value;
            if (!reason) {
                Swal.showValidationMessage('Please provide a reason');
                return false;
            }
            return fetch('report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `file_id=${fileId}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                return data;
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Reported!', 'The file has been reported to administrators', 'success');
        }
    });
}
