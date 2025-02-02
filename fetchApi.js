async function fetchAPI(endpoint, options = {}) {
    try {
        const response = await fetch(`api/endpoints.php?endpoint=${endpoint}`, {
            ...options,
            headers: {
                ...options.headers,
                'Content-Type': 'application/json'
            }
        });

        // Log the raw response for debugging
        const responseText = await response.text();
        console.log('Raw response:', responseText);

        try {
            const data = JSON.parse(responseText);
            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }
            return data;
        } catch (e) {
            console.error('JSON Parse Error:', e);
            throw new Error(`Invalid response format: ${responseText}`);
        }
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}
