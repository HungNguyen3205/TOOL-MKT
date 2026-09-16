export const previewImport = async (formData) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  
  const response = await fetch(`${baseUrl}/imports/preview`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json'
    },
    body: formData
  });
  
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const verifyDriveFolder = async (folder_url) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  
  const response = await fetch(`${baseUrl}/google-drive/verify-folder`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ folder_url })
  });
  
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const confirmImport = async (batchId, rows, actionOnDuplicate = 'skip') => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  
  const response = await fetch(`${baseUrl}/imports/confirm`, {
    method: 'POST',
    headers: { 
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      batch_id: batchId,
      rows,
      action_on_duplicate: actionOnDuplicate
    })
  });
  
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};
