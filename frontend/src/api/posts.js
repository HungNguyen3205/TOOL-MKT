export const fetchPosts = async (params = {}) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  const query = new URLSearchParams(params).toString();
  
  const response = await fetch(`${baseUrl}/posts?${query}`, {
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const fetchPost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  const response = await fetch(`${baseUrl}/posts/${id}`, {
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const createPost = async (payload) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
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
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
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
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  const response = await fetch(`${baseUrl}/posts/${id}`, {
    method: 'DELETE',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const duplicatePost = async (id) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  const response = await fetch(`${baseUrl}/posts/${id}/duplicate`, {
    method: 'POST',
    headers: { 'Accept': 'application/json' }
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};

export const changePostStatus = async (id, status) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
  const response = await fetch(`${baseUrl}/posts/${id}/status`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ status })
  });
  const data = await response.json();
  if (!response.ok) throw data;
  return data;
};
