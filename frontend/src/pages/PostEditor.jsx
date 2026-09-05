import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  fetchPost, createPost, updatePost, 
  qualityCheckPost, submitReviewPost, approvePost, requestChangesPost, markReadyPost, returnToDraftPost,
  fetchPostVersions, restorePostVersion, fetchPostActivities, generatePostImage, fetchPostImageStatus
} from '../api/posts';
import { getPublications } from '../api/facebook';
import FacebookPreview from '../components/FacebookPreview';
import toast from 'react-hot-toast';

const getApiErrorMessage = (err) => {
  if (err?.errors) {
    return Object.values(err.errors)
      .flat()
      .join('\n');
  }
  return err?.message || 'Dữ liệu không hợp lệ.';
};

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
  const [showScheduleModal, setShowScheduleModal] = useState(false);
  const [scheduleData, setScheduleData] = useState({ date: '', time: '' });
  
  const [reviewNote, setReviewNote] = useState('');
  
  const [logs, setLogs] = useState([]);
  const [versions, setVersions] = useState([]);
  const [activities, setActivities] = useState([]);
  const [loadingHistory, setLoadingHistory] = useState(false);
  const [showAdvancedPrompt, setShowAdvancedPrompt] = useState(false);
  
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
  const pollTimer = useRef(null);
  const currentPostId = useRef(id);

  const statusMap = {
    draft: { label: 'Bản nháp', color: '#757575' },
    generating_content: { label: 'Đang tạo nội dung', color: '#2196f3' },
    generating_image: { label: 'Đang tạo ảnh', color: '#2196f3' },
    ready: { label: 'Sẵn sàng', color: '#4caf50' },
    scheduled: { label: 'Đã lên lịch', color: '#ff9800' },
    publishing: { label: 'Đang đăng', color: '#ff9800' },
    published: { label: 'Đã đăng', color: '#0d47a1' },
    failed: { label: 'Lỗi đăng', color: '#f44336' },
    image_failed: { label: 'Lỗi tạo ảnh', color: '#f44336' },
    cancelled: { label: 'Đã hủy', color: '#757575' },
    in_review: { label: 'Chờ duyệt', color: '#2196f3' },
    changes_requested: { label: 'Cần chỉnh sửa', color: '#f44336' },
    approved: { label: 'Đã duyệt', color: '#9c27b0' }
  };

  useEffect(() => {
    if (!isNew) {
      loadPost(id);
      loadFacebookLogs(id);
    }
  }, [id, isNew]);

  useEffect(() => {
    if (post.status === 'generating_image') {
      startPollingImageStatus();
    } else {
      stopPollingImageStatus();
    }
    return () => stopPollingImageStatus();
  }, [post.status]);

  const startPollingImageStatus = () => {
    if (pollTimer.current) return;
    pollTimer.current = setInterval(async () => {
      if (!currentPostId.current) return;
      try {
        const res = await fetchPostImageStatus(currentPostId.current);
        const { status, image_url, error_message } = res.data;
        
        if (status === 'ready' || status === 'failed') {
          stopPollingImageStatus();
          await loadPost(currentPostId.current);
          if (status === 'failed') {
            toast.error(error_message || 'Tạo ảnh thất bại.');
          } else {
            toast.success('Đã tạo ảnh thành công!');
          }
        }
      } catch (err) {
        console.error(err);
      }
    }, 2000);
  };

  const stopPollingImageStatus = () => {
    if (pollTimer.current) {
      clearInterval(pollTimer.current);
      pollTimer.current = null;
    }
  };

  const loadFacebookLogs = async (postId) => {
    try {
     // Currently moving the history view to a dedicated page /posts/:id/publications
    // But if you still need it here:
    // const res = await getPublications(post.id);
    //  setLogs(res.data);
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
      console.error(err);
      toast.error('Không thể tải bài viết.');
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
      toast.error(getApiErrorMessage(err));
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

  const buildPayload = () => ({
    title: post.title?.trim() || '',
    content: post.content?.trim() || '',
    cta: post.cta?.trim() || null,
    hashtags: typeof post.hashtags === 'string'
      ? post.hashtags
          .split(/[, ]+/)
          .map(tag => tag.trim())
          .filter(Boolean)
      : Array.isArray(post.hashtags)
        ? post.hashtags
        : [],
    objective: post.objective || 'sales',
    tone: post.tone || 'friendly',
    content_length: post.content_length || 'medium',
    source:
      post.source === 'ai_generated'
        ? 'ai_edited'
        : post.source || 'manual',
    status: post.status || 'draft',
    ai_model: post.ai_model || null,
    ai_provider: post.ai_provider || null,
    selected_version: post.selected_version || null,
    image_prompt: post.image_prompt || '',
    source_input:
      post.source_input &&
      typeof post.source_input === 'object'
        ? post.source_input
        : null,
  });

  const savePost = async (isAutosave = false) => {
    if (!isDirty.current && isAutosave) return;
    if (['in_review', 'approved', 'ready'].includes(post.status)) {
       if (isAutosave) return;
       toast.error('Không thể lưu khi đang duyệt');
       return;
    }
    
    if (!isAutosave) {
        setSaving(true);
        if (debounceTimer.current) clearTimeout(debounceTimer.current);
    }
    
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
        toast.success("Đã lưu bài viết thành công!");
      }
    } catch (err) {
      console.error(err);
      setSaveStatus('Lưu thất bại - Thử lại');
      if (!isAutosave) toast.error(getApiErrorMessage(err));
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
      toast.success("Đã hoàn tất kiểm tra chất lượng.");
    } catch (err) {
      console.error(err);
      toast.error(getApiErrorMessage(err));
    } finally {
      setChecking(false);
    }
  };

  const handleSchedulePost = async () => {
    if (!scheduleData.date || !scheduleData.time) {
      toast.error("Vui lòng chọn ngày và giờ."); return;
    }
    const scheduleDatetime = `${scheduleData.date}T${scheduleData.time}:00`;
    if (new Date(scheduleDatetime) < new Date()) {
      toast.error("Thời gian đăng không được ở quá khứ."); return;
    }
    setSaving(true);
    try {
      await updatePost(currentPostId.current, { scheduled_at: scheduleDatetime, status: 'scheduled' });
      toast.success("Đã lên lịch thành công.");
      setShowScheduleModal(false);
      await loadPost(currentPostId.current);
    } catch (err) {
      console.error(err);
      toast.error(getApiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  const handleWorkflowAction = async (actionFn, successMsg, promptConfirm = null) => {
    if (!currentPostId.current) return;
    if (isDirty.current) await savePost(false);
    
    if (promptConfirm && !window.confirm(promptConfirm)) return;
    
    setSaving(true);
    try {
      await actionFn(currentPostId.current);
      toast.success(successMsg);
      await loadPost(currentPostId.current);
    } catch (err) {
      console.error(err);
      toast.error(getApiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  const handleRequestChanges = async () => {
    if (!reviewNote.trim()) { toast.error("Vui lòng nhập lý do."); return; }
    setSaving(true);
    try {
      await requestChangesPost(currentPostId.current, reviewNote);
      toast.success("Đã yêu cầu chỉnh sửa.");
      setShowReviewModal(false);
      setReviewNote('');
      await loadPost(currentPostId.current);
    } catch (err) {
      console.error(err);
      toast.error(getApiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  const handleRestoreVersion = async (vId) => {
    if (!window.confirm("Khôi phục phiên bản này sẽ tạo ra một phiên bản mới và ghi đè nội dung hiện tại. Tiếp tục?")) return;
    setSaving(true);
    try {
      await restorePostVersion(currentPostId.current, vId);
      toast.success("Khôi phục thành công.");
      await loadPost(currentPostId.current);
      await loadHistory();
    } catch (err) {
      console.error(err);
      toast.error(getApiErrorMessage(err));
    } finally {
      setSaving(false);
    }
  };

  const handleCopy = async () => {
    const textToCopy = `${post.title}\n\n${post.content}\n\n${post.cta || ''}\n\n${post.hashtags}`;
    try {
      await navigator.clipboard.writeText(textToCopy);
      toast.success('Đã sao chép nội dung');
    } catch (err) {
      console.error(err);
      toast.error('Không thể sao chép.');
    }
  };

  const handleRegenerateImage = async () => {
    setSaving(true);
    try {
      await generatePostImage(currentPostId.current, {
        prompt: post.image_prompt,
        regenerate: true
      });
      toast.success("Đang yêu cầu tạo lại ảnh...");
      setPost(prev => ({ ...prev, status: 'generating_image' }));
    } catch (err) {
      console.error(err);
      toast.error(getApiErrorMessage(err));
    } finally {
      setSaving(false);
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

      {/* SCHEDULE MODAL */}
      {showScheduleModal && (
        <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.8)', zIndex: 1000, display: 'flex', justifyContent: 'center', alignItems: 'center', padding: 20 }}>
          <div style={{ backgroundColor: '#222', width: '100%', maxWidth: '500px', borderRadius: 8, padding: 20 }}>
            <h3 style={{ marginTop: 0 }}>Lên lịch đăng Facebook</h3>
            <div className="form-group">
              <label>Ngày đăng</label>
              <input type="date" value={scheduleData.date} onChange={e => setScheduleData({...scheduleData, date: e.target.value})} style={{ width: '100%', padding: '8px', marginBottom: '10px' }} />
            </div>
            <div className="form-group">
              <label>Giờ đăng</label>
              <input type="time" value={scheduleData.time} onChange={e => setScheduleData({...scheduleData, time: e.target.value})} style={{ width: '100%', padding: '8px', marginBottom: '10px' }} />
            </div>
            <p style={{ fontSize: '0.9rem', color: '#aaa' }}>Múi giờ: Asia/Ho_Chi_Minh</p>
            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', marginTop: 20 }}>
              <button className="btn-secondary" onClick={() => setShowScheduleModal(false)}>Hủy</button>
              <button className="btn-primary" onClick={handleSchedulePost} disabled={saving}>Xác nhận lên lịch</button>
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

          {['draft', 'image_failed', 'changes_requested'].includes(post.status) && (
            <>
              <button className="btn-primary" onClick={() => savePost(false)} disabled={saving}>
                {saving ? 'Đang lưu...' : 'Lưu bản nháp'}
              </button>
              <button className="btn-primary" style={{ background: 'linear-gradient(135deg, #d946ef, #8b5cf6)', border: 'none', boxShadow: '0 4px 15px rgba(217, 70, 239, 0.4)', fontWeight: 'bold' }} onClick={() => handleWorkflowAction(() => updatePost(currentPostId.current, { ...buildPayload(), status: 'generating_content' }), "Đang tạo nội dung và hình ảnh...")} disabled={saving}>
                ✨ Tạo nội dung & hình ảnh
              </button>
              <button className="btn-secondary" style={{ background: 'rgba(255,255,255,0.05)', backdropFilter: 'blur(10px)', border: '1px solid rgba(255,255,255,0.2)', transition: 'all 0.3s' }} onClick={() => handleWorkflowAction(() => updatePost(currentPostId.current, { ...buildPayload(), status: 'generating_content' }), "Đang tạo lại nội dung...")} disabled={saving}>
                📝 Tạo lại nội dung
              </button>
              <button className="btn-secondary" style={{ background: 'rgba(59, 130, 246, 0.1)', color: '#60a5fa', border: '1px solid rgba(59, 130, 246, 0.3)', transition: 'all 0.3s' }} onClick={handleRegenerateImage} disabled={saving}>
                🎨 Tạo lại hình ảnh
              </button>
            </>
          )}

          {['generating_content', 'generating_image', 'publishing'].includes(post.status) && (
            <button className="btn-secondary" disabled>Đang xử lý...</button>
          )}

          {post.status === 'ready' && (
            <>
              <button className="btn-secondary" onClick={() => handleWorkflowAction(() => updatePost(currentPostId.current, { status: 'draft' }), "Đã đưa về bản nháp")}>Sửa lại</button>
              <button className="btn-primary" style={{ backgroundColor: '#ff9800' }} onClick={() => setShowScheduleModal(true)}>
                Lên lịch đăng
              </button>
              <button className="btn-success" onClick={() => handleWorkflowAction(() => updatePost(currentPostId.current, { status: 'publishing' }), "Đang tiến hành đăng...")}>
                Đăng ngay
              </button>
            </>
          )}

          {post.status === 'scheduled' && (
            <button className="btn-secondary" style={{ color: '#f44336', borderColor: '#f44336' }} onClick={() => handleWorkflowAction(() => updatePost(currentPostId.current, { status: 'ready' }), "Đã hủy lịch")}>
              Hủy lịch đăng
            </button>
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

          {post.status === 'image_failed' && post.generation_error && (
            <div style={{ backgroundColor: '#ffebee', color: '#c62828', padding: 15, borderRadius: 8, marginBottom: 20, borderLeft: '4px solid #f44336' }}>
              <strong>Lỗi tạo ảnh:</strong>
              <p style={{ margin: '5px 0 0' }}>{post.generation_error}</p>
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

          <div className="form-group" style={{ marginTop: '20px' }}>
            <button className="btn-secondary" style={{ width: '100%' }} onClick={() => setShowAdvancedPrompt(!showAdvancedPrompt)}>
              {showAdvancedPrompt ? 'Ẩn Prompt Hình Ảnh Nâng Cao' : 'Xem Prompt Hình Ảnh Nâng Cao'}
            </button>
            {showAdvancedPrompt && (
              <div style={{ marginTop: '10px' }}>
                <label>Image Prompt (Tùy chỉnh nếu cần)</label>
                <textarea 
                  name="image_prompt" 
                  value={post.image_prompt || ''} 
                  onChange={handleChange} 
                  rows="4" 
                  disabled={!isEditable} 
                  placeholder="Hệ thống sẽ tự tạo nếu để trống..."
                />
              </div>
            )}
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
            imageUrl={post.image_url}
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
