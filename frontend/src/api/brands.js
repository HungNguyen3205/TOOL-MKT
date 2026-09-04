export const fetchBrands = async (params = {}) => {
  const query = new URLSearchParams(params).toString();
  const res = await fetch(`/api/brands?${query}`);
  if (!res.ok) throw await res.json();
  return res.json();
};

export const fetchDefaultBrand = async () => {
  const res = await fetch('/api/brands/default');
  if (!res.ok) throw await res.json();
  return res.json();
};

export const fetchBrand = async (id) => {
  const res = await fetch(`/api/brands/${id}`);
  if (!res.ok) throw await res.json();
  return res.json();
};

export const createBrand = async (data) => {
  const res = await fetch('/api/brands', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const updateBrand = async (id, data) => {
  const res = await fetch(`/api/brands/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const deleteBrand = async (id) => {
  const res = await fetch(`/api/brands/${id}`, { method: 'DELETE' });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const setDefaultBrand = async (id) => {
  const res = await fetch(`/api/brands/${id}/default`, { method: 'PATCH' });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const toggleBrandStatus = async (id, isActive) => {
  const res = await fetch(`/api/brands/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ is_active: isActive }),
  });
  if (!res.ok) throw await res.json();
  return res.json();
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
