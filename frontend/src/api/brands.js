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
