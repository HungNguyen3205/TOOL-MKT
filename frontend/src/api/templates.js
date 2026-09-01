export const fetchTemplates = async (brandId, params = {}) => {
  const query = new URLSearchParams(params).toString();
  const res = await fetch(`/api/brands/${brandId}/templates?${query}`);
  if (!res.ok) throw await res.json();
  return res.json();
};

export const fetchTemplate = async (brandId, templateId) => {
  const res = await fetch(`/api/brands/${brandId}/templates/${templateId}`);
  if (!res.ok) throw await res.json();
  return res.json();
};

export const createTemplate = async (brandId, data) => {
  const res = await fetch(`/api/brands/${brandId}/templates`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const updateTemplate = async (brandId, templateId, data) => {
  const res = await fetch(`/api/brands/${brandId}/templates/${templateId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const deleteTemplate = async (brandId, templateId) => {
  const res = await fetch(`/api/brands/${brandId}/templates/${templateId}`, { method: 'DELETE' });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const setDefaultTemplate = async (brandId, templateId) => {
  const res = await fetch(`/api/brands/${brandId}/templates/${templateId}/default`, { method: 'PATCH' });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const toggleTemplateStatus = async (brandId, templateId, isActive) => {
  const res = await fetch(`/api/brands/${brandId}/templates/${templateId}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ is_active: isActive }),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};
