export const checkHealth = async () => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  try {
    const response = await fetch(`${baseUrl}/health`);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return await response.json();
  } catch (error) {
    console.error('Lỗi khi gọi API health check:', error);
    throw error;
  }
};

export const generateContent = async (payload) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  try {
    const response = await fetch(`${baseUrl}/content/generate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok) {
      throw data; // Throw the error object containing error_code and message
    }
    return data;
  } catch (error) {
    console.error('Lỗi API generate:', error);
    throw error;
  }
};
