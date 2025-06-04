document.getElementById('sitemapForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const url = document.getElementById('url').value;
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnText = document.getElementById('submitBtnText');
    const spinner = document.getElementById('spinner');
    const responseField = document.getElementById('response');
    
    // Function to update button text and show spinner
    function updateButton(text) {
        submitBtnText.textContent = text;
        spinner.style.display = 'inline-block';
    }

    responseField.value = '';
    submitBtn.disabled = true;
    updateButton('Fetching data...');

    try {
        const response = await fetch(`get_sitemap.php?url=${encodeURIComponent(url)}`);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        updateButton('Getting sitemap...');

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message);
        }

        updateButton('Parsing sitemap...');

        const cleanText = data.sitemap.join('\n');

        updateButton('Finalizing...');

        responseField.value = cleanText;
    } catch (error) {
        responseField.value = 'Error: ' + error.message;
    } finally {
        submitBtn.disabled = false;
        submitBtnText.textContent = 'Create Sitemap';
        spinner.style.display = 'none';
    }
});

function copyText() {
    var copyText = document.getElementById("response");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand("copy");
}