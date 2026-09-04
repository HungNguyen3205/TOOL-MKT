import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  fetchPost, createPost, updatePost, 
  qualityCheckPost, submitReviewPost, approvePost, requestChangesPost, markReadyPost, returnToDraftPost,
  fetchPostVersions, restorePostVersion, fetchPostActivities
} from '../api/posts';
import { getPublications } from '../api/facebook';
import FacebookPreview from '../components/FacebookPreview';

const PostEditor = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const isNew = !id;

  const [loading, setLoading] = useState(!isNew);
  const [saving, setSaving] = useState(false);
  const [checking, setChecking] = useState(false);
  const [saveStatus, setSaveStatus] = useState(''); // 'Đang lưu...', 'Đã lưu lúc HH:mm', 'Lưu thất bại'
  
  
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [showHistoryModal, setShowHistoryModal] = useState(false);
  
  const [reviewNote, setReviewNote] = useState('');
  
  const [logs, setLogs] = useState([]);
  const [versions, setVersions] = useState([]);
  const [activities, setActivities] = useState([]);
  const [loadingHistory, setLoadingHistory] = useState(false);
  
  const [post, setPost] = useState({
    title: '',
    content: '',
    cta: '',
    hashtags: '',
    objective: 'sales',
    tone: 'friendly',
    content_length: 'medium',
    status: 'draft',
    source: 'manual',
    quality_score: null,
    quality_status: 'unchecked',
    quality_result: null,
    content_version: 1
  });

  const isDirty = useRef(false);
  const debounceTimer = useRef(null);
  const currentPostId = useRef(id);

  const statusMap = {
    draft: { label: 'Bản nháp', color: '#757575' },
    in_review: { label: 'Chờ duyệt', color: '#2196f3' },
    changes_requested: { label: 'Cần chỉnh sửa', color: '#f44336' },
    approved: { label: 'Đã duyệt', color: '#9c27b0' },
    ready: { label: 'Sẵn sàng đăng', color: '#4caf50' }
  };

  useEffect(() => {
    if (!isNew) {
      loadPost(id);
      loadFacebookLogs(id);
    }
  }, [id, isNew]);

  const loadFacebookLogs = async (postId) => {
    try {
     // Currently moving the history view to a dedicated page /posts/:id/publications
    // But if you still need it here:
    // const res = await getPublications(post.id);
      setLogs(res.data);
    } catch (err) {
      console.error(err);
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
      isDirty.current = false;
    } catch (err) {
      alert('Không thể tải bài viết.');
      navigate('/posts');
    } finally {
      setLoading(false);
    }
  };

  const loadHistory = async () => {
    if (!currentPostId.current) return;
    setLoadingHistory(true);
    try {
      const [vRes, aRes] = await Promise.all([
        fetchPostVersions(currentPostId.current),
        fetchPostActivities(currentPostId.current)
      ]);
      setVersions(vRes.data);
      setActivities(aRes.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoadingHistory(false);
    }
  };

  const handleChange = (e) => {
    const { name, value } = e.target;
    setPost(prev => {
      const newState = { ...prev, [name]: value };
      if (['title', 'content', 'cta', 'hashtags'].includes(name)) {
         newState.quality_score = null;
         newState.quality_status = 'unchecked';
      }
      return newState;
    });
    isDirty.current = true;

    if (currentPostId.current && ['draft', 'changes_requested'].includes(post.status)) {
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
      hashtags: post.hashtags ? post.hashtags.split(',').map(s => s.trim()).filter(s => s) : [],
      source: post.source === 'ai_generated' ? 'ai_edited' : post.source
    };
  };

  const savePost = async (isAutosave = false) => {
    if (!isDirty.current && isAutosave) return;
    if (['in_review', 'approved', 'ready'].includes(post.status)) {
       setSaveStatus('Không thể lưu khi đang duyệt');
       return;
    }
    
    if (!isAutosave) setSaving(true);
    
    try {
      const payload = buildPayload();
      
      let savedPost;
      if (currentPostId.current) {
        const res = await updatePost(currentPostId.current, payload);
        savedPost = res.data;
      } else {
        const res = await createPost(payload);
        savedPost = res.data;
        currentPostId.current = savedPost.id;
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

  const handleQualityCheck = async () => {
    if (!currentPostId.current) {
      alert("Vui lòng lưu bản nháp trước khi kiểm tra."); return;
    }
    if (isDirty.current) await savePost(false);
    
    setChecking(true);
    try {
      const res = await qualityCheckPost(currentPostId.current);
      setPost(prev => ({
        ...prev,
        quality_score: res.data.score,
        quality_status: res.data.status,
        quality_result: res.data
      }));
      alert("Đã hoàn tất kiểm tra chất lượng.");
    } catch (err) {
      alert("Lỗi khi kiểm tra chất lượng.");
    } finally {
      setChecking(false);
    }
  };

  const handleWorkflowAction = async (actionFn, successMsg, promptConfirm = null) => {
    if (!currentPostId.current) return;
    if (isDirty.current) await savePost(false);
    
    if (promptConfirm && !window.confirm(promptConfirm)) return;
    
    setSaving(true);
    try {
      const res = await actionFn(currentPostId.current);
      alert(successMsg);
      await loadPost(currentPostId.current);
    } catch (err) {
      alert(err.message || 'Lỗi không xác định.');
    } finally {
      setSaving(false);
    }
  };

  const handleRequestChanges = async () => {
    if (!reviewNote.trim()) { alert("Vui lòng nhập lý do."); return; }
    setSaving(true);
    try {
      await requestChangesPost(currentPostId.current, reviewNote);
      alert("Đã yêu cầu chỉnh sửa.");
      setShowReviewModal(false);
      setReviewNote('');
      await loadPost(currentPostId.current);
    } catch (err) {
      alert(err.message || 'Lỗi khi yêu cầu.');
    } finally {
      setSaving(false);
    }
  };

  const handleRestoreVersion = async (vId) => {
    if (!window.confirm("Khôi phục phiên bản này sẽ tạo ra một phiên bản mới và ghi đè nội dung hiện tại. Tiếp tục?")) return;
    setSaving(true);
    try {
      await restorePostVersion(currentPostId.current, vId);
      alert("Khôi phục thành công.");
      await loadPost(currentPostId.current);
      await loadHistory();
    } catch (err) {
      alert(err.message || 'Lỗi khi khôi phục.');
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

  const isEditable = ['draft', 'changes_requested'].includes(post.status);
  const isPendingReview = post.status === 'in_review';

  return (
    <div className="post-editor-page">
      {/* HISTORY MODAL */}
      {showHistoryModal && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.8)', zIndex: 1000, display: 'flex', justifyContent: 'center', alignItems: 'center', padding: 20 }}>
          <div style={{ backgroundColor: '#222', width: '100%', maxWidth: '800px', maxHeight: '90vh', overflowY: 'auto', borderRadius: 8, padding: 20 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
              <h3>Lịch sử phiên bản & Hoạt động</h3>
              <button className="btn-secondary" onClick={() => setShowHistoryModal(false)}>Đóng</button>
            </div>
            
            {loadingHistory ? <p>Đang tải...</p> : (
              <div style={{ display: 'flex', gap: 20 }}>
                <div style={{ flex: 1 }}>
                  <h4>Các phiên bản</h4>
                  {versions.map(v => (
                    <div key={v.id} style={{ border: '1px solid #444', padding: 10, marginBottom: 10, borderRadius: 5 }}>
                      <strong>V{v.version_number}: {v.change_source}</strong>
                      <div style={{ fontSize: '0.85rem', color: '#aaa', margin: '5px 0' }}>{new Date(v.created_at).toLocaleString()} - Điểm: {v.quality_score || 'N/A'}</div>
                      <p style={{ fontSize: '0.85rem', margin: '5px 0' }}>{v.change_summary}</p>
                      {isEditable && (
                        <button className="btn-secondary" style={{ padding: '2px 8px', fontSize: '0.8rem' }} onClick={() => handleRestoreVersion(v.id)}>Khôi phục V{v.version_number}</button>
                      )}
                    </div>
                  ))}
                </div>
                <div style={{ flex: 1 }}>
                  <h4>Nhật ký hoạt động</h4>
                  {activities.map(a => (
                    <div key={a.id} style={{ borderLeft: '2px solid #555', paddingLeft: 10, marginBottom: 15 }}>
                      <strong style={{ display: 'block' }}>{a.action}</strong>
                      {a.from_status && <span style={{ fontSize: '0.8rem', color: '#999' }}>{a.from_status} &rarr; {a.to_status}</span>}
                      <div style={{ fontSize: '0.75rem', color: '#666', marginTop: 3 }}>{new Date(a.created_at).toLocaleString()}</div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* REVIEW MODAL */}
      {showReviewModal && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.8)', zIndex: 1000, display: 'flex', justifyContent: 'center', alignItems: 'center', padding: 20 }}>
          <div style={{ backgroundColor: '#222', width: '100%', maxWidth: '500px', borderRadius: 8, padding: 20 }}>
            <h3 style={{ marginTop: 0 }}>Yêu cầu chỉnh sửa</h3>
            <p>Vui lòng nhập lý do hoặc hướng dẫn để người viết sửa lại:</p>
            <textarea 
              rows="4" 
              style={{ width: '100%', padding: 10, marginBottom: 15, backgroundColor: '#333', color: '#fff', border: '1px solid #555', borderRadius: 4 }} 
              placeholder="Ví dụ: CTA chưa có link, bổ sung thêm hotline..."
              value={reviewNote}
              onChange={e => setReviewNote(e.target.value)}
            />
            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end' }}>
              <button className="btn-secondary" onClick={() => setShowReviewModal(false)}>Hủy</button>
              <button className="btn-primary" style={{ backgroundColor: '#f44336' }} onClick={handleRequestChanges} disabled={saving}>Gửi yêu cầu</button>
            </div>
          </div>
        </div>
      )}

      <div className="editor-header">
        <div className="header-left">
          <button className="btn-secondary" onClick={() => navigate('/posts')}>&larr; Trở lại</button>
          <h2>{isNew ? 'Tạo bài mới' : `Bài viết (V${post.content_version})`}</h2>
          <span style={{ backgroundColor: statusMap[post.status]?.color, padding: '4px 10px', borderRadius: 20, fontSize: '0.85rem', fontWeight: 'bold' }}>
            {statusMap[post.status]?.label}
          </span>
        </div>
        <div className="header-actions">
          <span className="save-status">{saveStatus}</span>
          
          {!isNew && (
            <button className="btn-secondary" onClick={() => { setShowHistoryModal(true); loadHistory(); }}>
              Lịch sử
            </button>
          )}

          {isEditable && (
            <>
              <button className="btn-primary" onClick={() => savePost(false)} disabled={saving}>
                {saving ? 'Đang lưu...' : 'Lưu bản nháp'}
              </button>
              <button className="btn-secondary" onClick={handleQualityCheck} disabled={checking}>
                {checking ? 'Đang kiểm tra...' : 'Kiểm tra chất lượng'}
              </button>
              <button 
                className="btn-primary" 
                style={{ backgroundColor: '#ff9800' }} 
                onClick={() => handleWorkflowAction(submitReviewPost, "Đã gửi duyệt", post.quality_status === 'warning' ? "Bài viết có cảnh báo. Vẫn tiếp tục gửi duyệt?" : null)}
                disabled={saving || post.quality_score === null}
              >
                Gửi duyệt
              </button>
            </>
          )}

          {isPendingReview && (
            <>
              <button className="btn-secondary" style={{ borderColor: '#f44336', color: '#f44336' }} onClick={() => setShowReviewModal(true)}>
                Yêu cầu chỉnh sửa
              </button>
              <button className="btn-success" onClick={() => handleWorkflowAction(approvePost, "Đã duyệt thành công!")} disabled={saving}>
                Duyệt bài này
              </button>
            </>
          )}

          {post.status === 'approved' && (
            <>
              <button className="btn-secondary" onClick={() => handleWorkflowAction(returnToDraftPost, "Đã đưa về bản nháp")}>Sửa lại</button>
              <button className="btn-success" onClick={() => handleWorkflowAction(markReadyPost, "Đã đánh dấu sẵn sàng")} disabled={saving}>
                Sẵn sàng đăng
              </button>
            </>
          )}

          {post.status === 'ready' && (
            <>
              <button className="btn-secondary" onClick={() => handleWorkflowAction(returnToDraftPost, "Đã đưa về bản nháp")}>Sửa lại</button>
              <button className="btn-primary" onClick={() => navigate(`/posts/${currentPostId.current}/publish`)} style={{backgroundColor: '#1877f2'}}>
                Đăng lên Facebook
              </button>
            </>
          )}
        </div>
      </div>

      <div className="editor-layout">
        <div className="editor-form">
          {post.review_note && ['changes_requested', 'draft'].includes(post.status) && (
            <div style={{ backgroundColor: '#3e2723', padding: 15, borderRadius: 8, marginBottom: 20, borderLeft: '4px solid #ff9800' }}>
              <strong>Yêu cầu chỉnh sửa từ người duyệt:</strong>
              <p style={{ margin: '5px 0 0' }}>{post.review_note}</p>
            </div>
          )}

          {post.quality_score === null && !isNew && (
             <div style={{ backgroundColor: '#333', padding: 15, borderRadius: 8, marginBottom: 20, color: '#ffb300' }}>
               Nội dung đã bị thay đổi, vui lòng <strong>Kiểm tra chất lượng</strong> lại trước khi gửi duyệt!
             </div>
          )}

          {post.quality_result && post.quality_score !== null && (
            <div style={{ marginBottom: '20px', padding: '15px', backgroundColor: '#f9f9f9', borderRadius: '8px', borderLeft: `4px solid ${post.quality_status === 'passed' ? '#4caf50' : (post.quality_status === 'warning' ? '#ff9800' : '#f44336')}` }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <strong style={{ color: '#333' }}>Đánh giá AI: {post.quality_score}/100 điểm</strong>
              </div>
              {post.quality_result.errors && post.quality_result.errors.length > 0 && (
                <div style={{ color: '#d32f2f', fontSize: '0.9rem', marginTop: '8px' }}>
                  <strong>Lỗi nghiêm trọng:</strong>
                  <ul style={{ margin: '4px 0', paddingLeft: '20px' }}>
                    {post.quality_result.errors.map((e, i) => <li key={i}>{e}</li>)}
                  </ul>
                </div>
              )}
              {post.quality_result.warnings && post.quality_result.warnings.length > 0 && (
                <div style={{ color: '#f57c00', fontSize: '0.9rem', marginTop: '8px' }}>
                  <strong>Cảnh báo:</strong>
                  <ul style={{ margin: '4px 0', paddingLeft: '20px' }}>
                    {post.quality_result.warnings.map((w, i) => <li key={i}>{w}</li>)}
                  </ul>
                </div>
              )}
            </div>
          )}

          <div className="form-group">
            <label>Tiêu đề *</label>
            <input type="text" name="title" value={post.title} onChange={handleChange} required disabled={!isEditable} />
          </div>
          
          <div className="form-group">
            <label>Nội dung chính *</label>
            <textarea name="content" value={post.content} onChange={handleChange} rows="10" required disabled={!isEditable} />
            <small>{post.content.length} ký tự</small>
          </div>
          
          <div className="form-group">
            <label>Call To Action (CTA)</label>
            <input type="text" name="cta" value={post.cta} onChange={handleChange} disabled={!isEditable} />
          </div>
          
          <div className="form-group">
            <label>Hashtags</label>
            <input type="text" name="hashtags" value={post.hashtags} onChange={handleChange} placeholder="#omachi, #ngon" disabled={!isEditable} />
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
              <div className="empty-state" style={{padding: 20}}>
                <button className="btn-secondary" onClick={() => navigate(`/posts/${currentPostId.current}/publications`)}>
                  Xem lịch sử đăng bài chi tiết
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default PostEditor;
