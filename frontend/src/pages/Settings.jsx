import React, { useState, useEffect } from 'react';
import { fetchSettings, updateSettings } from '../api/settings';

const Settings = () => {
  const [settings, setSettings] = useState({
    FACEBOOK_APP_ID: '',
    FACEBOOK_APP_SECRET: '',
    FACEBOOK_REDIRECT_URI: 'http://localhost:8000/api/facebook/callback'
  });
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    const loadSettings = async () => {
      try {
        const res = await fetchSettings();
        if (res.data) {
          setSettings(prev => ({
            ...prev,
            ...res.data
          }));
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    loadSettings();
  }, []);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setSettings(prev => ({ ...prev, [name]: value }));
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage('');
    try {
      await updateSettings(settings);
      setMessage('Lưu cấu hình thành công!');
    } catch (err) {
      setMessage('Lỗi khi lưu cấu hình.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div className="loading">Đang tải...</div>;

  return (
    <div className="post-list-page">
      <div className="page-header">
        <h2>Cài đặt Hệ thống</h2>
      </div>

      <div style={{ maxWidth: '800px', backgroundColor: '#1e1e1e', padding: '20px', borderRadius: '8px' }}>
        <h3>Kết nối Meta (Facebook)</h3>
        <p style={{ color: '#aaa', fontSize: '0.9rem', marginBottom: '20px' }}>
          Để ứng dụng có thể đăng bài lên Facebook, bạn cần tạo một ứng dụng trên 
          <a href="https://developers.facebook.com/" target="_blank" rel="noreferrer" style={{ color: '#4caf50', marginLeft: '5px' }}>
            Meta for Developers
          </a> và lấy App ID cùng App Secret điền vào đây.
        </p>

        {message && (
          <div style={{ padding: '10px', marginBottom: '20px', borderRadius: '4px', backgroundColor: message.includes('thành công') ? '#4caf50' : '#f44336', color: '#fff' }}>
            {message}
          </div>
        )}

        <form onSubmit={handleSave}>
          <div className="form-group">
            <label>Facebook App ID</label>
            <input 
              type="text" 
              name="FACEBOOK_APP_ID" 
              value={settings.FACEBOOK_APP_ID} 
              onChange={handleChange} 
              placeholder="VD: 1234567890" 
            />
          </div>

          <div className="form-group">
            <label>Facebook App Secret</label>
            <input 
              type="password" 
              name="FACEBOOK_APP_SECRET" 
              value={settings.FACEBOOK_APP_SECRET} 
              onChange={handleChange} 
              placeholder="VD: abc123def456..." 
            />
          </div>

          <div className="form-group">
            <label>Valid OAuth Redirect URI (Copy link này dán vào cấu hình Facebook Login)</label>
            <input 
              type="text" 
              name="FACEBOOK_REDIRECT_URI" 
              value={settings.FACEBOOK_REDIRECT_URI} 
              onChange={handleChange} 
              readOnly
              style={{ backgroundColor: '#333', color: '#aaa' }}
            />
          </div>

          <div className="post-actions" style={{ marginTop: '20px', justifyContent: 'flex-start' }}>
            <button type="submit" className="btn-primary" disabled={saving}>
              {saving ? 'Đang lưu...' : 'Lưu cấu hình'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default Settings;
