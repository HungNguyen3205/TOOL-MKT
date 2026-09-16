import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL + '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Interceptor to add workspace_id to headers
api.interceptors.request.use((config) => {
  const workspaceId = localStorage.getItem('selected_workspace_id');
  if (workspaceId) {
    config.headers['X-Workspace-Id'] = workspaceId;
  }
  return config;
});

export const commentAPI = {
  getScheduled: async (page = 1) => {
    const response = await api.get('/comments/scheduled', { params: { page } });
    return response.data;
  },

  schedule: async (data) => {
    const response = await api.post('/comments/scheduled', data);
    return response.data;
  },

  deleteScheduled: async (id) => {
    const response = await api.delete(`/comments/scheduled/${id}`);
    return response.data;
  },

  getLiveComments: async (publicationId) => {
    const response = await api.get(`/comments/live/${publicationId}`);
    return response.data;
  },

  reply: async (data) => {
    const response = await api.post('/comments/reply', data);
    return response.data;
  }
};
