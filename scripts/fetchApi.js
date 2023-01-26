async function fetchAPI(endpoint, options = {}) {
  const defaultOptions = {
    credentials: "include", // Important for sending cookies
    headers: {
      "Content-Type": "application/json",
    },
  };

  try {
    const response = await fetch(`api/endpoints.php?endpoint=${endpoint}`, {
      ...defaultOptions,
      ...options,
    });

    if (!response.ok) {
      if (response.status === 401) {
        window.location.href = "auth/login.php";
        return;
      }
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error("API Error:", error);
    throw error;
  }
}
