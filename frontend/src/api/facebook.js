export const getAuthUrl = async () => {
  const res = await fetch('/api/facebook/auth-url');
  if (!res.ok) throw await res.json();
  return res.json();
};

export const getAvailablePages = async (sessionId) => {
  const res = await fetch(`/api/facebook/available-pages?session_id=${sessionId}`);
  if (!res.ok) throw await res.json();
  return res.json();
};

export const connectPage = async (sessionId, pageId) => {
  const res = await fetch('/api/facebook/pages/connect', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ session_id: sessionId, page_id: pageId }),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const getConnectedPages = async () => {
  const res = await fetch('/api/facebook/pages');
  if (!res.ok) throw await res.json();
  return res.json();
};

export const verifyPage = async (id) => {
  const res = await fetch(`/api/facebook/pages/${id}/verify`, { method: 'POST' });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const disconnectPage = async (id) => {
  const res = await fetch(`/api/facebook/pages/${id}`, { method: 'DELETE' });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const publishPost = async (postId, facebookPageId) => {
  const res = await fetch(`/api/posts/${postId}/publish`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ facebook_page_id: facebookPageId, confirmation: true }),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};

export const getPublicationLogs = async (postId) => {
  const res = await fetch(`/api/posts/${postId}/publication-logs`);
  if (!res.ok) throw await res.json();
  return res.json();
};
