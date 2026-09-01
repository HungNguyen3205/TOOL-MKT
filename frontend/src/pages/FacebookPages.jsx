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
      alert('Kết nối Page thành công!');
      // Remove from available and refresh connected
      setAvailablePages(prev => prev.filter(p => p.id !== pageId));
      loadConnectedPages();
    } catch (err) {
      alert(err.message || 'Lỗi kết nối Page');
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
      loadConnectedPages();
    }
  };

  const handleDisconnect = async (id, name) => {
    if (window.confirm(`Bạn có chắc muốn ngắt kết nối page "${name}"?`)) {
      try {
        await disconnectPage(id);
        loadConnectedPages();
      } catch (err) {
        alert('Lỗi khi ngắt kết nối.');
      }
    }
  };

  return (
    <div className="post-list-page">
      <div className="page-header">
        <h2>Kết nối Facebook Pages</h2>
        <button onClick={handleStartConnect} className="btn-primary" disabled={authStatus === 'connecting'}>
          {authStatus === 'connecting' ? 'Đang chuyển hướng...' : '+ Thêm kết nối Facebook'}
        </button>
      </div>

      <div style={{marginBottom: 20}}>
        <small style={{color: 'gray'}}>
          Ứng dụng yêu cầu quyền: <code>pages_show_list</code> (xem danh sách page), <code>pages_manage_posts</code> (đăng bài), <code>pages_read_engagement</code> (đọc tương tác).<br/>
          <em>* Lưu ý: Hiện tại hệ thống đang chạy trong môi trường nội bộ, chỉ kết nối các Page thuộc tài khoản Test.</em>
        </small>
      </div>

      {errorMsg && <div className="error-alert">{errorMsg}</div>}

      {availablePages.length > 0 && (
        <div style={{marginBottom: 40}}>
          <h3>Pages có thể kết nối (từ phiên đăng nhập hiện tại)</h3>
          <div className="post-grid">
            {availablePages.map(page => (
              <div key={page.id} className="post-card" style={{display: 'flex', alignItems: 'center', gap: 15}}>
                {page.picture_url && <img src={page.picture_url} alt="Page" style={{width: 50, height: 50, borderRadius: '50%'}} />}
                <div style={{flex: 1}}>
                  <h4>{page.name}</h4>
                  <small>{page.id}</small>
                </div>
                <button onClick={() => handleConnectPage(page.sessionId, page.id)} className="btn-primary">
                  Kết nối
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      <h3>Các Page đang kết nối</h3>
      <div className="post-grid">
        {loading && !connectedPages.length ? (
          <div className="loading">Đang tải...</div>
        ) : connectedPages.length === 0 ? (
          <div className="empty-state">Chưa có Facebook Page nào được kết nối.</div>
        ) : (
          connectedPages.map(page => (
            <div key={page.id} className="post-card">
              <div style={{display: 'flex', alignItems: 'center', gap: 10, marginBottom: 15}}>
                {page.page_picture_url && <img src={page.page_picture_url} alt="Page" style={{width: 40, height: 40, borderRadius: '50%'}} />}
                <h4 style={{margin: 0}}>{page.page_name}</h4>
              </div>
              
              <div className="post-meta">
                <span className={`status ${page.connection_status === 'connected' ? 'badge-ready' : 'badge-draft'}`}>
                  {page.connection_status === 'connected' ? 'Đang kết nối' : 'Lỗi / Hết hạn'}
                </span>
                <br/><br/>
                <small>Kiểm tra lần cuối: {new Date(page.last_verified_at).toLocaleString()}</small>
              </div>

              {page.connection_status !== 'connected' && page.last_error_message && (
                <div style={{color: 'red', fontSize: 12, marginTop: 10}}>
                  Lỗi: {page.last_error_message}
                </div>
              )}

              <div className="post-actions" style={{marginTop: 15}}>
                <button onClick={() => handleVerify(page.id)} className="btn-secondary">Kiểm tra kết nối</button>
                <button onClick={() => handleDisconnect(page.id, page.page_name)} className="btn-danger">Ngắt kết nối</button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default FacebookPages;
