function initiateOAuth() {
    const appKey = document.querySelector('input[name="app_key"]').value;
    const appSecret = document.querySelector('input[name="app_secret"]').value;
    
    // Encode credentials in state parameter
    const state = btoa(JSON.stringify({
        app_key: appKey,
        app_secret: appSecret
    }));
    
    const redirectUri = encodeURIComponent(window.location.origin + '/callback.php');
    window.location.href = `https://www.dropbox.com/oauth2/authorize?client_id=${appKey}&response_type=code&redirect_uri=${redirectUri}&token_access_type=offline&state=${state}`;
}
