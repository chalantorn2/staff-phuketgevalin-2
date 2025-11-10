export async function sendApiRequest(
  url,
  method,
  apiKey,
  apiVersion,
  body = null
) {
  const headers = {
    API_KEY: apiKey,
    "Content-Type": "application/json",
    Accept: "application/json",
    VERSION: apiVersion,
  };

  const options = {
    method: method,
    headers: headers,
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(url, options);
    const timestamp = new Date().toISOString();
    const data = await response.json();

    // เพิ่ม console.log ตรงนี้ ↓
    console.log("URL:", url);
    console.log("Headers:", options.headers);
    console.log("Body:", options.body);
    console.log("Response Status:", response.status);
    console.log("Response Data:", data);

    return {
      status: response.status,
      timestamp: timestamp,
      apiKey: apiKey,
      responseData: data,
    };
  } catch (error) {
    return {
      status: 500,
      timestamp: new Date().toISOString(),
      apiKey: apiKey,
      error: error.message,
    };
  }
}
