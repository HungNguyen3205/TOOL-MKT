export const fetchPosts = async (params = {}) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const query = new URLSearchParams(params).toString();
  
  const response = await fetch(`${baseUrl}/posts?${query}`, {
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const fetchPost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}`, {
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const createPost = async (payload) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify(payload)
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const updatePost = async (id, payload) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify(payload)
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const deletePost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}`, {
    method: 'DELETE',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const duplicatePost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/duplicate`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const qualityCheckPost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/quality-check`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const submitReviewPost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/submit-review`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const approvePost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/approve`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const requestChangesPost = async (id, note) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/request-changes`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ note })
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const markReadyPost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/mark-ready`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const returnToDraftPost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/return-to-draft`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const fetchPostVersions = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/versions`, {
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const restorePostVersion = async (postId, versionId) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${postId}/versions/${versionId}/restore`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const fetchPostActivities = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api';
  const response = await fetch(`${baseUrl}/posts/${id}/activities`, {
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};
