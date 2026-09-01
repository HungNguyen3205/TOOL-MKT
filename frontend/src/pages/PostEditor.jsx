import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { fetchPost, createPost, updatePost, changePostStatus } from '../api/posts';
import { getPublicationLogs } from '../api/facebook';
import FacebookPreview from '../components/FacebookPreview';
import PublishModal from '../components/PublishModal';

const PostEditor = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const isNew = !id;

  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [saveStatus, setSaveStatus] = useState(''); // 'Đang lưu...', 'Đã lưu lúc HH:mm', 'Lưu thất bại'
  const [showPublishModal, setShowPublishModal] = useState(false);
  const [logs, setLogs] = useState([]);
  const [loadingLogs, setLoadingLogs] = useState(false);
  
  const [post, setPost] = useState({
    title: '',
    content: '',
    cta: '',
    hashtags: '',
    objective: 'sales',
    tone: 'friendly',
    content_length: 'medium',
    status: 'draft',
    source: 'manual'
  });

  // Track if content actually changed to avoid unnecessary autosaves
  const isDirty = useRef(false);
  const debounceTimer = useRef(null);
  const currentPostId = useRef(id);

  useEffect(() => {
    if (!isNew) {
      loadPost(id);
      loadLogs(id);
    }
  }, [id, isNew]);

  const loadLogs = async (postId) => {
    setLoadingLogs(true);
    try {
      const res = await getPublicationLogs(postId);
      setLogs(res.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingLogs(false);
    }
  };

  const loadPost = async (postId) => {
    try {
      const res = await fetchPost(postId);
      const data = res.data;
      setPost({
        ...data,
        hashtags: Array.isArray(data.hashtags) ? data.hashtags.join(', ') : (data.hashtags || '')
      });
      currentPostId.current = postId;
    } catch (err) {
      alert('Không thể tải bài viết.');
      navigate('/posts');
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setPost(prev => ({ ...prev, [name]: value }));
    isDirty.current = true;

    // Trigger Autosave only if not new
    if (currentPostId.current) {
      triggerAutosave();
    }
  };

  const triggerAutosave = () => {
    if (debounceTimer.current) clearTimeout(debounceTimer.current);
    setSaveStatus('Đang lưu...');
    
    debounceTimer.current = setTimeout(() => {
      savePost(true);
    }, 2000);
  };

  const buildPayload = () => {
    return {
      ...post,
      hashtags: post.hashtags ? post.hashtags.split(',').map(s => s.trim()).filter(s => s) : []
    };
  };

  const savePost = async (isAutosave = false) => {
    if (!isDirty.current && isAutosave) return;
    
    if (!isAutosave) setSaving(true);
    
    try {
      const payload = buildPayload();
      
      let savedPost;
      if (currentPostId.current) {
        // Update
        // if user edited an AI post, update source to ai_edited
        if (post.source === 'ai_generated') {
          payload.source = 'ai_edited';
        }
        const res = await updatePost(currentPostId.current, payload);
        savedPost = res.data;
      } else {
        // Create
        const res = await createPost(payload);
        savedPost = res.data;
        currentPostId.current = savedPost.id;
        // Update URL without reloading
        window.history.replaceState(null, '', `/posts/${savedPost.id}/edit`);
      }

      isDirty.current = false;
      
      const time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
      setSaveStatus(`Đã lưu lúc ${time}`);
      
      if (!isAutosave) {
        setPost({
          ...savedPost,
          hashtags: Array.isArray(savedPost.hashtags) ? savedPost.hashtags.join(', ') : ''
        });
      }
    } catch (err) {
      console.error(err);
      setSaveStatus('Lưu thất bại - Thử lại');
      if (!isAutosave) alert('Lỗi khi lưu bài viết.');
    } finally {
      if (!isAutosave) setSaving(false);
    }
  };

  const handleStatusChange = async (newStatus) => {
    if (!currentPostId.current) {
      alert('Vui lòng lưu bài viết trước khi đổi trạng thái.');
      return;
    }
    
    if (newStatus === 'ready' && (!post.title || !post.content)) {
      alert('Bài viết phải có ít nhất Tiêu đề và Nội dung chính để sẵn sàng.');
      return;
    }

    try {
      setSaving(true);
      await changePostStatus(currentPostId.current, newStatus);
      setPost(prev => ({ ...prev, status: newStatus }));
      alert('Đã cập nhật trạng thái.');
    } catch (err) {
      alert('Không thể cập nhật trạng thái.');
    } finally {
      setSaving(false);
    }
  };

  const handleCopy = async () => {
    const textToCopy = `${post.title}\n\n${post.content}\n\n${post.cta || ''}\n\n${post.hashtags}`;
    try {
      await navigator.clipboard.writeText(textToCopy);
      alert('Đã sao chép nội dung');
    } catch (err) {
      alert('Không thể sao chép.');
    }
  };

  if (loading) return <div className="loading">Đang tải...</div>;

  return (
    <div className="post-editor-page">
      <div className="editor-header">
        <div className="header-left">
          <button className="btn-secondary" onClick={() => navigate('/posts')}>&larr; Quay lại</button>
          <h2>{isNew ? 'Tạo bài viết thủ công' : 'Chỉnh sửa bài viết'}</h2>
        </div>
        <div className="header-actions">
          <span className="save-status">{saveStatus}</span>
          <button className="btn-primary" onClick={() => savePost(false)} disabled={saving}>
            {saving ? 'Đang lưu...' : 'Lưu bản nháp'}
          </button>
          {post.status === 'draft' ? (
            <button className="btn-success" onClick={() => handleStatusChange('ready')} disabled={saving}>
              Đánh dấu Sẵn sàng
            </button>
          ) : (
            <>
              <button className="btn-secondary" onClick={() => handleStatusChange('draft')} disabled={saving}>
                Chuyển về Bản nháp
              </button>
              <button className="btn-primary" onClick={() => setShowPublishModal(true)} style={{marginLeft: 10, backgroundColor: '#1877f2'}}>
                Đăng lên Facebook
              </button>
            </>
          )}
        </div>
      </div>

      <div className="editor-layout">
        <div className="editor-form">
          <div className="form-group">
            <label>Tiêu đề *</label>
            <input type="text" name="title" value={post.title} onChange={handleChange} required />
          </div>
          
          <div className="form-group">
            <label>Nội dung chính *</label>
            <textarea name="content" value={post.content} onChange={handleChange} rows="10" required />
            <small>{post.content.length} ký tự</small>
          </div>
          
          <div className="form-group">
            <label>Call To Action (CTA)</label>
            <input type="text" name="cta" value={post.cta} onChange={handleChange} />
          </div>
          
          <div className="form-group">
            <label>Hashtags</label>
            <input type="text" name="hashtags" value={post.hashtags} onChange={handleChange} placeholder="#omachi, #ngon" />
          </div>

          <button className="btn-secondary" onClick={handleCopy} style={{width: '100%', marginTop: 20}}>
            Sao chép toàn bộ
          </button>
        </div>

        <div className="editor-preview">
          <h3>Xem trước Facebook</h3>
          <FacebookPreview 
            title={post.title} 
            content={post.content} 
            cta={post.cta} 
            hashtags={post.hashtags ? post.hashtags.split(',').map(s=>s.trim()).filter(s=>s) : []} 
          />

          {!isNew && (
            <div style={{marginTop: 40}}>
              <h3>Lịch sử đăng Facebook</h3>
              {loadingLogs ? (
                <div>Đang tải...</div>
              ) : logs.length === 0 ? (
                <div className="empty-state" style={{padding: 20}}>Chưa đăng lên Facebook lần nào.</div>
              ) : (
                <div style={{display: 'flex', flexDirection: 'column', gap: 10}}>
                  {logs.map(log => (
                    <div key={log.id} style={{backgroundColor: '#222', padding: 15, borderRadius: 8}}>
                      <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: 5}}>
                        <strong>{log.facebook_page?.page_name || 'Không xác định'}</strong>
                        <span className={`badge-${log.status === 'success' ? 'ready' : (log.status === 'failed' ? 'draft' : 'draft')}`}>
                          {log.status}
                        </span>
                      </div>
                      <small style={{display: 'block', color: 'gray'}}>Ngày thử: {new Date(log.attempted_at).toLocaleString()}</small>
                      {log.facebook_post_id && (
                        <small style={{display: 'block', color: 'gray'}}>Post ID: {log.facebook_post_id}</small>
                      )}
                      {log.error_message && (
                        <small style={{display: 'block', color: '#ffb3b3'}}>Lỗi: {log.error_message}</small>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {showPublishModal && (
        <PublishModal 
          post={{...post, id: currentPostId.current}} 
          onClose={() => setShowPublishModal(false)}
          onSuccess={(data) => {
            setShowPublishModal(false);
            loadPost(currentPostId.current);
            loadLogs(currentPostId.current);
          }}
        />
      )}
    </div>
  );
};

export default PostEditor;
