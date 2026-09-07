import React, { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { getAuthUrl, getAvailablePages, connectPage, getConnectedPages, verifyPage, disconnectPage } from '../api/facebook';

const FacebookPages = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const [connectedPages, setConnectedPages] = useState([]);
  const [availablePages, setAvailablePages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [authStatus, setAuthStatus] = useState(null); // 'connecting', 'success', 'error'
  const [errorMsg, setErrorMsg] = useState(null);
  const [viewMode, setViewMode] = useState('grid'); // 'grid' or 'list'

  useEffect(() => {
    loadConnectedPages();

    // Check OAuth Callback status in URL
    const status = searchParams.get('status');
    if (status === 'error') {
      const errCode = searchParams.get('error_code');
      const errMsg = searchParams.get('message');
      setAuthStatus('error');
      setErrorMsg(`Kết nối thất bại. Mã lỗi: ${errCode}. Chi tiết: ${errMsg || 'Không có'}`);
      setSearchParams({}); // Clear url
    } else if (status === 'success') {
      const sessionId = searchParams.get('session_id');
      if (sessionId) {
        setAuthStatus('success');
        loadAvailablePages(sessionId);
        setSearchParams({}); // Clear url
      }
    }
  }, []);

  const loadConnectedPages = async () => {
    setLoading(true);
    try {
      const res = await getConnectedPages();
      setConnectedPages(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const loadAvailablePages = async (sessionId) => {
    setLoading(true);
    try {
      const res = await getAvailablePages(sessionId);
      setAvailablePages(res.data.map(p => ({ ...p, sessionId })));
    } catch (err) {
      setErrorMsg(err.message || 'Không thể tải danh sách page khả dụng.');
    } finally {
      setLoading(false);
    }
  };

  const handleStartConnect = async () => {
    try {
      setAuthStatus('connecting');
      const res = await getAuthUrl();
      window.location.href = res.data.url;
    } catch (err) {
      setAuthStatus('error');
      setErrorMsg('Không thể tạo URL kết nối.');
    }
  };

  const handleConnectPage = async (sessionId, pageId) => {
    setLoading(true);
    try {
      await connectPage(sessionId, pageId);
      setAvailablePages(prev => prev.filter(page => page.id !== pageId));
      await loadConnectedPages();
      alert('Kết nối Page thành công!');
    } catch (err) {
      alert(err.message || 'Không thể lưu Facebook Page vào danh sách kết nối.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerify = async (id) => {
    try {
      const res = await verifyPage(id);
      alert(res.message);
      loadConnectedPages();
    } catch (err) {
      alert(err.message || 'Token không hợp lệ.');
    }
  };

  const handleDisconnect = async (id) => {
    if (!window.confirm("Bạn có chắc chắn muốn ngắt kết nối Page này?")) return;
    try {
      await disconnectPage(id);
      alert('Ngắt kết nối thành công');
      loadConnectedPages();
    } catch (err) {
      alert(err.message || 'Lỗi ngắt kết nối.');
    }
  };

  // Mock grouping for presentation
  const groupedPages = {
    'Chưa phân nhóm': connectedPages,
    // You can add logic to group pages based on a property later
  };

  return (
    <div className="dn-fb-pages">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--dn-space-6)' }}>
        <div>
          <h2 style={{ margin: 0, fontSize: 'var(--dn-text-2xl)', color: 'var(--dn-text-primary)' }}>Facebook Pages</h2>
          <p style={{ margin: 'var(--dn-space-2) 0 0 0', color: 'var(--dn-text-secondary)' }}>Quản lý các tài khoản Fanpage được kết nối với hệ thống.</p>
        </div>
        <div style={{ display: 'flex', gap: 'var(--dn-space-4)' }}>
          <div style={{ display: 'flex', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-md)', overflow: 'hidden' }}>
            <button 
              style={{ padding: '8px 12px', background: viewMode === 'grid' ? 'var(--dn-bg-surface-hover)' : 'var(--dn-bg-surface)', border: 'none', cursor: 'pointer', color: 'var(--dn-text-primary)' }}
              onClick={() => setViewMode('grid')}
            >
              ⊞ Grid
            </button>
            <div style={{ width: '1px', background: 'var(--dn-border-color)' }}></div>
            <button 
              style={{ padding: '8px 12px', background: viewMode === 'list' ? 'var(--dn-bg-surface-hover)' : 'var(--dn-bg-surface)', border: 'none', cursor: 'pointer', color: 'var(--dn-text-primary)' }}
              onClick={() => setViewMode('list')}
            >
              ☰ List
            </button>
          </div>
          <button className="dn-btn dn-btn-primary" onClick={handleStartConnect} disabled={loading}>
            {loading ? 'Đang kết nối...' : '+ Kết nối Fanpage mới'}
          </button>
        </div>
      </div>

      {authStatus === 'error' && (
        <div style={{ padding: '15px', backgroundColor: 'var(--dn-color-danger)', color: 'white', borderRadius: '8px', marginBottom: '20px' }}>
          {errorMsg}
        </div>
      )}

      {availablePages.length > 0 && (
        <div style={{ backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-6)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)', marginBottom: 'var(--dn-space-8)' }}>
          <h3 style={{ margin: '0 0 var(--dn-space-4) 0' }}>Pages khả dụng để kết nối</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--dn-space-2)' }}>
            {availablePages.map(page => (
              <div key={page.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: 'var(--dn-space-3)', backgroundColor: 'var(--dn-bg-app)', borderRadius: 'var(--dn-radius-md)', border: '1px solid var(--dn-border-color)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
                  <img src={page.picture_url} alt="Page avatar" style={{ width: '40px', height: '40px', borderRadius: '50%' }} />
                  <div>
                    <strong>{page.name}</strong>
                    <div style={{ fontSize: '12px', color: 'var(--dn-text-secondary)' }}>ID: {page.id}</div>
                  </div>
                </div>
                <button className="dn-btn dn-btn-primary" onClick={() => handleConnectPage(page.sessionId, page.id)}>
                  Kết nối ngay
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {Object.entries(groupedPages).map(([groupName, pages]) => (
        <div key={groupName} style={{ marginBottom: 'var(--dn-space-8)' }}>
          <h3 style={{ margin: '0 0 var(--dn-space-4) 0', color: 'var(--dn-text-secondary)', borderBottom: '1px solid var(--dn-border-color)', paddingBottom: 'var(--dn-space-2)' }}>
            {groupName} ({pages.length})
          </h3>
          
          {pages.length === 0 ? (
            <p style={{ color: 'var(--dn-text-tertiary)' }}>Không có page nào.</p>
          ) : (
            <div style={{ 
              display: viewMode === 'grid' ? 'grid' : 'flex',
              flexDirection: viewMode === 'grid' ? 'unset' : 'column',
              gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', 
              gap: 'var(--dn-space-4)' 
            }}>
              {pages.map(page => (
                <div key={page.id} style={{
                  backgroundColor: 'var(--dn-bg-surface)',
                  border: '1px solid var(--dn-border-color)',
                  borderRadius: 'var(--dn-radius-lg)',
                  padding: 'var(--dn-space-5)',
                  display: viewMode === 'list' ? 'flex' : 'block',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  boxShadow: 'var(--dn-shadow-sm)',
                  transition: 'transform var(--dn-transition-fast)'
                }}
                onMouseOver={e => e.currentTarget.style.transform = 'translateY(-2px)'}
                onMouseOut={e => e.currentTarget.style.transform = 'none'}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--dn-space-4)', marginBottom: viewMode === 'grid' ? 'var(--dn-space-4)' : 0 }}>
                    <div style={{ width: '60px', height: '60px', borderRadius: '50%', backgroundColor: 'var(--dn-bg-app)', display: 'flex', justifyContent: 'center', alignItems: 'center', overflow: 'hidden' }}>
                      {page.avatar_url ? (
                        <img src={page.avatar_url} alt="avatar" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                      ) : (
                        <span style={{ fontSize: '24px' }}>📱</span>
                      )}
                    </div>
                    <div>
                      <h4 style={{ margin: '0 0 5px 0', fontSize: 'var(--dn-text-lg)', color: 'var(--dn-text-primary)' }}>{page.page_name}</h4>
                      <div style={{ display: 'flex', gap: '10px', alignItems: 'center', fontSize: 'var(--dn-text-sm)' }}>
                        <span style={{ color: page.status === 'active' ? 'var(--dn-color-success)' : 'var(--dn-color-danger)', fontWeight: 'bold' }}>
                          ● {page.status === 'active' ? 'Đã kết nối' : 'Mất kết nối'}
                        </span>
                      </div>
                    </div>
                  </div>
                  
                  <div style={{ display: 'flex', gap: 'var(--dn-space-2)', marginTop: viewMode === 'grid' ? 'var(--dn-space-4)' : 0 }}>
                    <button className="dn-btn" onClick={() => handleVerify(page.id)} style={{ flex: viewMode === 'grid' ? 1 : 'unset', border: '1px solid var(--dn-border-color)' }}>
                      Kiểm tra
                    </button>
                    <button className="dn-btn" onClick={() => handleDisconnect(page.id)} style={{ flex: viewMode === 'grid' ? 1 : 'unset', border: '1px solid var(--dn-color-danger)', color: 'var(--dn-color-danger)' }}>
                      Ngắt kết nối
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ))}
    </div>
  );
};

export default FacebookPages;
