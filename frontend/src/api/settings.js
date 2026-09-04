export const fetchSettings = async () => {
  const res = await fetch('/api/settings');
  if (!res.ok) throw await res.json();
  return res.json();
};

export const updateSettings = async (settings) => {
  const res = await fetch('/api/settings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ settings }),
  });
  if (!res.ok) throw await res.json();
  return res.json();
};
