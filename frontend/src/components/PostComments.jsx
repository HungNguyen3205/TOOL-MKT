import React, { useState, useEffect } from 'react';
import toast from 'react-hot-toast';

export default function PostComments({ postId, postStatus }) {
  const [comments, setComments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all'); // all, draft, scheduled, published, failed
  const [content, setContent] = useState('');
  const [scheduleData, setScheduleData] = useState({ date: '', time: '' });
  const [showSchedule, setShowSchedule] = useState(false);
  const [scheduleType, setScheduleType] = useState('absolute'); // absolute, after_post
  const [delayMinutes, setDelayMinutes] = useState(5);

  const fetchComments = async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/posts/${postId}/comments`, {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        setComments(data.data);
      }
    } catch (err) {
      console.error(err);
      toast.error('Lỗi khi tải bình luận');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (postId) {
      fetchComments();
    }
  }, [postId]);

  const handleCreate = async (statusOverride = 'draft', extraPayload = {}) => {
    if (!content.trim()) {
      toast.error('Vui lòng nhập nội dung bình luận');
      return;
    }

    let payload = { content, ...extraPayload };
    
    if (statusOverride === 'scheduled') {
      if (scheduleType === 'absolute') {
        if (!scheduleData.date || !scheduleData.time) {
          toast.error('Vui lòng chọn ngày và giờ.');
          return;
        }
        payload.scheduled_at = `${scheduleData.date}T${scheduleData.time}:00`;
      } else {
        payload.schedule_type = 'after_post';
        payload.delay_minutes = delayMinutes;
      }
    }

    try {
      const res = await fetch(`/api/posts/${postId}/comments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message);
        setContent('');
        setShowSchedule(false);
        fetchComments();
        return data.data; // Return comment for further action
      } else {
        toast.error(data.message || 'Có lỗi xảy ra');
      }
    } catch (err) {
      toast.error('Lỗi mạng');
    }
  };

  const handlePublishNow = async (commentId) => {
    try {
      const res = await fetch(`/api/comments/${commentId}/publish-now`, {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message);
      } else {
        toast.error(data.message || 'Lỗi khi đăng');
      }
      fetchComments();
    } catch (err) {
      toast.error('Lỗi hệ thống');
    }
  };

  const handleCancelSchedule = async (commentId) => {
    try {
      const res = await fetch(`/api/comments/${commentId}/cancel`, {
        method: 'POST',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message);
      } else {
        toast.error(data.message || 'Lỗi hủy lịch');
      }
      fetchComments();
    } catch (err) {
      toast.error('Lỗi hệ thống');
    }
  };

  const handleDelete = async (commentId) => {
    if (!window.confirm('Chắc chắn xóa bình luận này?')) return;
    try {
      const res = await fetch(`/api/comments/${commentId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data.success) {
        toast.success(data.message);
        fetchComments();
      } else {
        toast.error(data.message || 'Lỗi xóa');
      }
    } catch (err) {
      toast.error('Lỗi hệ thống');
    }
  };

  const filteredComments = comments.filter(c => filter === 'all' || c.status === filter);

  return (
    <div className="post-comments-tab">
      <div className="comments-filter" style={{ display: 'flex', gap: '10px', marginBottom: '20px' }}>
        {['all', 'draft', 'scheduled', 'published', 'failed'].map(f => (
          <button 
            key={f} 
            className={`btn-secondary ${filter === f ? 'active' : ''}`}
            onClick={() => setFilter(f)}
            style={filter === f ? { backgroundColor: '#333', color: '#fff', borderColor: '#fff' } : {}}
          >
            {f === 'all' ? 'Tất cả' : 
             f === 'draft' ? 'Bản nháp' :
             f === 'scheduled' ? 'Đã lên lịch' :
             f === 'published' ? 'Đã đăng' : 'Thất bại'}
          </button>
        ))}
      </div>

      <div className="comment-composer" style={{ backgroundColor: '#222', padding: '15px', borderRadius: '8px', marginBottom: '20px' }}>
        <textarea 
          placeholder="Nhập nội dung bình luận..."
          value={content}
          onChange={e => setContent(e.target.value)}
          rows="3"
          style={{ width: '100%', padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #444', marginBottom: '10px' }}
        />
        
        {showSchedule && (
          <div style={{ padding: '10px', backgroundColor: '#333', borderRadius: '4px', marginBottom: '10px' }}>
            <div style={{ marginBottom: '10px' }}>
              <label><input type="radio" checked={scheduleType === 'absolute'} onChange={() => setScheduleType('absolute')} /> Chọn ngày giờ cụ thể</label>
              <label style={{ marginLeft: '15px' }}><input type="radio" checked={scheduleType === 'after_post'} onChange={() => setScheduleType('after_post')} /> Đăng sau bài viết</label>
            </div>
            {scheduleType === 'absolute' ? (
              <div style={{ display: 'flex', gap: '10px' }}>
                <input type="date" value={scheduleData.date} onChange={e => setScheduleData({...scheduleData, date: e.target.value})} style={{ padding: '5px' }} />
                <input type="time" value={scheduleData.time} onChange={e => setScheduleData({...scheduleData, time: e.target.value})} style={{ padding: '5px' }} />
              </div>
            ) : (
              <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                Đăng sau: 
                <select value={delayMinutes} onChange={e => setDelayMinutes(parseInt(e.target.value))} style={{ padding: '5px' }}>
                  <option value={5}>5 phút</option>
                  <option value={15}>15 phút</option>
                  <option value={30}>30 phút</option>
                  <option value={60}>60 phút</option>
                </select>
              </div>
            )}
            <div style={{ display: 'flex', gap: '10px', marginTop: '10px' }}>
              <button className="btn-primary" onClick={() => handleCreate('scheduled')}>Xác nhận lên lịch</button>
              <button className="btn-secondary" onClick={() => setShowSchedule(false)}>Hủy</button>
            </div>
          </div>
        )}

        {!showSchedule && (
          <div style={{ display: 'flex', gap: '10px' }}>
            <button className="btn-primary" onClick={async () => {
              const created = await handleCreate('draft');
              if (created && created.id) {
                handlePublishNow(created.id);
              }
            }} style={{ 
              background: content.trim() ? '#4ade80' : 'var(--success-color)', 
              opacity: content.trim() ? 1 : 0.7,
              boxShadow: content.trim() ? '0 0 10px rgba(74, 222, 128, 0.5)' : 'none',
              transform: content.trim() ? 'scale(1.05)' : 'scale(1)',
              transition: 'all 0.3s ease'
            }}>Đăng ngay</button>
            <button className="btn-primary" onClick={() => {
              handleCreate('draft');
            }}>Lưu nháp</button>
            <button className="btn-secondary" onClick={() => setShowSchedule(true)}>Lên lịch</button>
          </div>
        )}
      </div>

      <div className="comments-list" style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
        {loading ? <p>Đang tải...</p> : filteredComments.length === 0 ? <p style={{ color: '#888' }}>Chưa có bình luận nào.</p> : filteredComments.map(c => (
          <div key={c.id} style={{ backgroundColor: '#222', padding: '15px', borderRadius: '8px', border: `1px solid ${c.status === 'published' ? '#4caf50' : c.status === 'failed' ? '#f44336' : '#ff9800'}` }}>
            <div style={{ whiteSpace: 'pre-wrap', marginBottom: '10px' }}>{c.content}</div>
            <div style={{ fontSize: '0.85rem', color: '#aaa', display: 'flex', justifyContent: 'space-between' }}>
              <span>Trạng thái: {c.status} {c.scheduled_at && `- Lên lịch lúc: ${new Date(c.scheduled_at).toLocaleString()}`} {c.published_at && `- Đã đăng lúc: ${new Date(c.published_at).toLocaleString()}`}</span>
              <span style={{ display: 'flex', gap: '10px' }}>
                {['draft', 'failed', 'cancelled'].includes(c.status) && <button onClick={() => handlePublishNow(c.id)} style={{ background: 'none', border: 'none', color: '#2196f3', cursor: 'pointer' }}>Đăng ngay</button>}
                {c.status === 'scheduled' && <button onClick={() => handleCancelSchedule(c.id)} style={{ background: 'none', border: 'none', color: '#f44336', cursor: 'pointer' }}>Hủy lịch</button>}
                {['draft', 'failed', 'cancelled'].includes(c.status) && <button onClick={() => handleDelete(c.id)} style={{ background: 'none', border: 'none', color: '#f44336', cursor: 'pointer' }}>Xóa</button>}
                {c.facebook_comment_url && <a href={c.facebook_comment_url} target="_blank" rel="noreferrer" style={{ color: '#2196f3' }}>Mở Facebook</a>}
              </span>
            </div>
            {c.status === 'failed' && <div style={{ color: '#f44336', fontSize: '0.8rem', marginTop: '5px' }}>Lỗi: {c.last_error_message}</div>}
          </div>
        ))}
      </div>
    </div>
  );
}
