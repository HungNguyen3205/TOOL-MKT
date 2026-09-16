const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api';

const parseApiResponse = async (res) => {
  const contentType = res.headers.get('content-type');
  let data;
  if (contentType && contentType.includes('application/json')) {
    data = await res.json();
  } else {
    data = { message: 'Lỗi phản hồi từ máy chủ (không phải JSON).', status: res.status };
  }

  if (!res.ok) {
    throw data;
  }
  return data;
};

export const fetchBrands = async (params = {}) => {
  const query = new URLSearchParams(params).toString();
  const res = await fetch(`${API_BASE}/brands?${query}`);
  return parseApiResponse(res);
};

export const fetchDefaultBrand = async () => {
  const res = await fetch(`${API_BASE}/brands/default`);
  return parseApiResponse(res);
};

export const fetchBrand = async (id) => {
  const res = await fetch(`${API_BASE}/brands/${id}`);
  return parseApiResponse(res);
};

export const createBrand = async (data) => {
  const res = await fetch(`${API_BASE}/brands`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data),
  });
  return parseApiResponse(res);
};

export const updateBrand = async (id, data) => {
  const res = await fetch(`${API_BASE}/brands/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data),
  });
  return parseApiResponse(res);
};

export const deleteBrand = async (id) => {
  const res = await fetch(`${API_BASE}/brands/${id}`, { method: 'DELETE' });
  return parseApiResponse(res);
};

export const setDefaultBrand = async (id) => {
  const res = await fetch(`${API_BASE}/brands/${id}/default`, { method: 'PATCH' });
  return parseApiResponse(res);
};

export const toggleBrandStatus = async (id, isActive) => {
  const res = await fetch(`${API_BASE}/brands/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ is_active: isActive }),
  });
  return parseApiResponse(res);
};

// Versions
export const fetchBrandVersions = async (id) => {
  const res = await fetch(`/api/brands/${id}/versions`);
  if (!res.ok) throw await res.json();
  return res.json();
};
export const restoreBrandVersion = async (id, versionId) => {
  const res = await fetch(`/api/brands/${id}/versions/${versionId}/restore`, { method: 'POST' });
  if (!res.ok) throw await res.json();
  return res.json();
};

// Knowledge
export const fetchBrandKnowledge = async (brandId) => {
  const res = await fetch(`/api/brands/${brandId}/knowledge`);
  if (!res.ok) throw await res.json();
  return res.json();
};
export const createBrandKnowledge = async (brandId, data) => {
  const res = await fetch(`/api/brands/${brandId}/knowledge`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};
export const updateBrandKnowledge = async (brandId, itemId, data) => {
  const res = await fetch(`/api/brands/${brandId}/knowledge/${itemId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};
export const deleteBrandKnowledge = async (brandId, itemId) => {
  const res = await fetch(`/api/brands/${brandId}/knowledge/${itemId}`, { method: 'DELETE' });
  if (!res.ok) throw await res.json();
  return res.json();
};

// Examples
export const fetchBrandExamples = async (brandId) => {
  const res = await fetch(`/api/brands/${brandId}/examples`);
  if (!res.ok) throw await res.json();
  return res.json();
};
export const createBrandExample = async (brandId, data) => {
  const res = await fetch(`/api/brands/${brandId}/examples`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};
export const updateBrandExample = async (brandId, itemId, data) => {
  const res = await fetch(`/api/brands/${brandId}/examples/${itemId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};
export const deleteBrandExample = async (brandId, itemId) => {
  const res = await fetch(`/api/brands/${brandId}/examples/${itemId}`, { method: 'DELETE' });
  if (!res.ok) throw await res.json();
  return res.json();
};
