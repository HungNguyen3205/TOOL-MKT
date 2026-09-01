import React, { useState, useEffect } from 'react';
import { getConnectedPages, publishPost } from '../api/facebook';

const PublishModal = ({ post, onClose, onSuccess }) => {
  const [pages, setPages] = useState([]);
  const [selectedPageId, setSelectedPageId] = useState('');
  const [loading, setLoading] = useState(true);
  const [publishing, setPublishing] = useState(false);
  const [confirmed, setConfirmed] = useState(false);
  const [errorMsg, setErrorMsg] = useState(null);

  useEffect(() => {
    const fetchPages = async () => {
      try {
        const res = await getConnectedPages();
        const activePages = res.data.filter(p => p.connection_status === 'connected');
        setPages(activePages);
        if (activePages.length > 0) {
          setSelectedPageId(activePages[0].id.toString());
        }
      } catch (err) {
        setErrorMsg('Lỗi khi tải danh sách Page.');
      } finally {
        setLoading(false);
      }
    };
    fetchPages();
  }, []);

  const handlePublish = async () => {
    if (!selectedPageId) {
      setErrorMsg('Vui lòng chọn Page.');
      return;
    }
    if (!confirmed) {
      setErrorMsg('Vui lòng xác nhận trước khi đăng.');
      return;
    }

    setPublishing(true);
    setErrorMsg(null);
    try {
      const res = await publishPost(post.id, selectedPageId);
      alert(res.message);
      onSuccess(res.data);
    } catch (err) {
      setErrorMsg(err.message || 'Có lỗi xảy ra khi đăng bài.');
    } finally {
      setPublishing(false);
    }
  };

  // Build the formatted text as the backend does
  const buildPreview = () => {
    const parts = [];
    if (post.title) parts.push(post.title);
    if (post.content) parts.push(post.content);
    if (post.cta) parts.push(post.cta);
    
    if (post.hashtags && post.hashtags.length > 0) {
      const tags = post.hashtags.map(t => t.startsWith('#') ? t : `#${t}`);
      parts.push(tags.join(' '));
    }
    return parts.join('\n\n');
  };

  return (
    <div className="modal-overlay">
      <div className="modal-content" style={{maxWidth: 600}}>
        <h2>Đăng bài lên Facebook</h2>
        
        {loading ? (
          <div className="loading">Đang tải danh sách Pages...</div>
        ) : pages.length === 0 ? (
          <div>
            <div className="error-alert">Bạn chưa có Facebook Page nào khả dụng hoặc đang bị lỗi token.</div>
            <button onClick={onClose} className="btn-secondary">Đóng</button>
          </div>
        ) : (
          <>
            <div className="form-group">
              <label>Chọn Facebook Page</label>
              <select value={selectedPageId} onChange={(e) => setSelectedPageId(e.target.value)} disabled={publishing}>
                {pages.map(p => (
                  <option key={p.id} value={p.id}>{p.page_name}</option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label>Bản xem trước Nội dung (Text format chính xác)</label>
              <div className="preview-box" style={{maxHeight: 250, overflowY: 'auto', backgroundColor: '#111', padding: 15, borderRadius: 8, whiteSpace: 'pre-wrap', fontSize: 14}}>
                {buildPreview()}
              </div>
            </div>

            <div className="form-group" style={{display: 'flex', alignItems: 'center', gap: 10, marginTop: 20}}>
              <input 
                type="checkbox" 
                id="confirmPublish" 
                checked={confirmed} 
                onChange={(e) => setConfirmed(e.target.checked)} 
                disabled={publishing}
              />
              <label htmlFor="confirmPublish" style={{margin: 0, fontWeight: 'normal', color: '#ffb3b3'}}>
                Tôi xác nhận đây là thao tác đăng bài trực tiếp lên Facebook thật.
              </label>
            </div>

            {errorMsg && <div className="error-alert" style={{marginTop: 15}}>{errorMsg}</div>}

            <div className="post-actions" style={{marginTop: 30, justifyContent: 'flex-end'}}>
              <button onClick={onClose} className="btn-secondary" disabled={publishing}>Hủy</button>
              <button onClick={handlePublish} className="btn-primary" disabled={publishing || !confirmed || !selectedPageId}>
                {publishing ? 'Đang đăng...' : 'Xác nhận Đăng'}
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default PublishModal;
