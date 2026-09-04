import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getPublications, getAllPublications, retryPublication } from '../api/facebook';

const PublicationHistory = () => {
  const { id } = useParams();
  const [publications, setPublications] = useState([]);
  const [loading, setLoading] = useState(true);
  const [errorMsg, setErrorMsg] = useState(null);

  const fetchHistory = async () => {
    try {
      const res = id ? await getPublications(id) : await getAllPublications();
      setPublications(res.data);
    } catch (err) {
      setErrorMsg('Không thể tải lịch sử đăng bài.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchHistory();
    // Poll every 5 seconds if there's any queued or processing publication
    const interval = setInterval(() => {
      setPublications(prev => {
        const needsPolling = prev.some(p => p.status === 'queued' || p.status === 'processing');
        if (needsPolling) {
          fetchHistory();
        }
        return prev;
      });
    }, 5000);

    return () => clearInterval(interval);
  }, [id]);

  const handleRetry = async (pubId) => {
    try {
      setPublications(prev => prev.map(p => p.id === pubId ? { ...p, status: 'queued' } : p));
      await retryPublication(pubId);
      fetchHistory();
    } catch (err) {
      alert(err.message || 'Lỗi khi thử lại.');
      fetchHistory();
    }
  };

  const getStatusBadge = (status) => {
    const style = { whiteSpace: 'nowrap', display: 'inline-block' };
    switch (status) {
      case 'published': return <span className="badge-ready" style={style}>Thành công</span>;
      case 'failed': return <span className="badge-draft" style={{backgroundColor: '#f44336', ...style}}>Lỗi</span>;
      case 'processing': return <span className="badge-draft" style={{backgroundColor: '#ff9800', ...style}}>Đang xử lý</span>;
      case 'queued': return <span className="badge-draft" style={{backgroundColor: '#9e9e9e', ...style}}>Trong hàng đợi</span>;
      default: return <span className="badge-draft" style={style}>{status}</span>;
    }
  };

  return (
    <div className="post-list-page">
      <div className="page-header">
        <h2>{id ? `Lịch sử Đăng Facebook (Bài viết #${id})` : 'Tất cả Lịch sử Đăng bài'}</h2>
        {id && <Link to="/posts" className="btn-secondary">Quay lại</Link>}
      </div>
      
      {errorMsg && <div className="error-alert">{errorMsg}</div>}

      <div className="post-grid" style={{ display: 'block' }}>
        {loading ? (
          <div className="loading">Đang tải...</div>
        ) : publications.length === 0 ? (
          <div className="empty-state">Bài viết này chưa được đăng lên trang nào.</div>
        ) : (
            <table className="table" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ borderBottom: '1px solid #333', textAlign: 'left' }}>
                  {!id && <th style={{ padding: '10px' }}>Bài viết</th>}
                  <th style={{ padding: '10px' }}>Facebook Page</th>
                  <th style={{ padding: '10px' }}>Trạng thái</th>
                  <th style={{ padding: '10px' }}>Lần thử</th>
                  <th style={{ padding: '10px' }}>Lỗi cuối</th>
                  <th style={{ padding: '10px' }}>Cập nhật</th>
                  <th style={{ padding: '10px' }}>Hành động</th>
                </tr>
              </thead>
              <tbody>
              {publications.map(pub => (
                <tr key={pub.id} style={{ borderBottom: '1px solid #222' }}>
                  {!id && (
                    <td style={{ padding: '10px' }}>
                      <Link to={`/posts/${pub.post_id}/edit`} style={{ color: '#2196f3', textDecoration: 'none' }}>
                        {pub.post?.title || `Bài viết #${pub.post_id}`}
                      </Link>
                    </td>
                  )}
                  <td style={{ padding: '10px' }}>{pub.facebook_page?.page_name}</td>
                  <td style={{ padding: '10px' }}>{getStatusBadge(pub.status)}</td>
                  <td style={{ padding: '10px' }}>{pub.attempts_count}</td>
                  <td style={{ padding: '10px', color: '#ffb3b3', fontSize: '13px' }}>
                    {pub.last_error_message || (pub.status === 'published' ? '-' : '')}
                  </td>
                  <td style={{ padding: '10px', fontSize: '13px' }}>
                    {new Date(pub.updated_at).toLocaleString()}
                  </td>
                  <td style={{ padding: '10px' }}>
                    {pub.status === 'failed' && (
                      <button onClick={() => handleRetry(pub.id)} className="btn-secondary" style={{ padding: '4px 8px', fontSize: '12px' }}>
                        Thử lại
                      </button>
                    )}
                    {pub.external_post_id && (
                      <a href={`https://facebook.com/${pub.external_post_id}`} target="_blank" rel="noreferrer" style={{ marginLeft: '10px', color: '#4caf50' }}>
                        Xem bài
                      </a>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};

export default PublicationHistory;
