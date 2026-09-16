import React, { useState, useEffect } from 'react';
import { commentAPI } from '../api/comments';
import { toast } from 'react-hot-toast';
import moment from 'moment';
import 'moment/locale/vi';

moment.locale('vi');

export default function CommentManagement() {
  const [filter, setFilter] = useState('all'); // all, draft, scheduled, published, error
  
  const [publications, setPublications] = useState([]);
  const [scheduledComments, setScheduledComments] = useState([]);
  const [loadingComments, setLoadingComments] = useState(false);
  const [liveComments, setLiveComments] = useState([]);
  const [syncing, setSyncing] = useState(false);

  // Composer Form
  const [scheduleForm, setScheduleForm] = useState({
    publication_id: '',
    message: '',
    scheduled_at: moment().add(5, 'minutes').format('YYYY-MM-DDTHH:mm'),
  });

  useEffect(() => {
    fetchPublications();
    fetchScheduled();
  }, []);

  const fetchPublications = async () => {
    try {
      const res = await fetch('/api/publications?status=published');
      if (res.ok) {
        const data = await res.json();
        if (data.data) {
          setPublications(data.data.filter(p => p.external_post_id));
        }
      }
    } catch (e) {
      console.error(e);
    }
  };

  const fetchScheduled = async () => {
    setLoadingComments(true);
    try {
      const res = await commentAPI.getScheduled();
      setScheduledComments(res.data);
    } catch (err) {
      toast.error('Lỗi khi tải bình luận lên lịch');
    } finally {
      setLoadingComments(false);
    }
  };

  const handleSyncFacebook = async () => {
    if (!scheduleForm.publication_id) {
      toast.error('Vui lòng chọn bài viết để đồng bộ');
      return;
    }
    setSyncing(true);
    try {
      const res = await commentAPI.getLiveComments(scheduleForm.publication_id);
      if (res.success) {
        setLiveComments(res.data);
        toast.success('Đồng bộ thành công');
      } else {
        toast.error(res.message);
      }
    } catch (err) {
      toast.error('Lỗi khi tải bình luận trực tiếp');
    } finally {
      setSyncing(false);
    }
  };

  const handleScheduleSubmit = async (e) => {
    if (e) e.preventDefault();
    if (!scheduleForm.publication_id || !scheduleForm.message.trim()) return;
    try {
      await commentAPI.schedule(scheduleForm);
      toast.success('Đã lên lịch bình luận!');
      setScheduleForm({ ...scheduleForm, message: '' });
      fetchScheduled();
    } catch (err) {
      toast.error('Lỗi khi lên lịch: ' + (err.response?.data?.message || err.message));
    }
  };

  const handleLiveReply = async (e) => {
    if (e) e.preventDefault();
    if (!scheduleForm.publication_id || !scheduleForm.message.trim()) return;
    const targetId = publications.find(p => p.id === parseInt(scheduleForm.publication_id))?.external_post_id;
    try {
      const res = await commentAPI.reply({
        publication_id: scheduleForm.publication_id,
        target_comment_id: targetId,
        message: scheduleForm.message
      });
      if (res.success) {
        toast.success('Đã đăng bình luận ngay lập tức');
        setScheduleForm({ ...scheduleForm, message: '' });
        handleSyncFacebook();
      } else {
        toast.error(res.message);
      }
    } catch (err) {
      toast.error('Lỗi khi gửi bình luận');
    }
  };

  const handleSaveDraft = (e) => {
    if (e) e.preventDefault();
    toast.success('Đã lưu nháp (cục bộ)');
    // Just a UI placeholder as requested
  };

  const handleDeleteScheduled = async (id) => {
    if (!window.confirm('Bạn có chắc muốn xóa?')) return;
    try {
      await commentAPI.deleteScheduled(id);
      toast.success('Đã xóa bình luận');
      fetchScheduled();
    } catch (err) {
      toast.error('Lỗi khi xóa');
    }
  };

  // Merge scheduled and live comments into a unified list for display
  const unifiedComments = [
    ...scheduledComments.map(c => ({
      id: 'sch_' + c.id,
      real_id: c.id,
      type: 'scheduled',
      message: c.message,
      status: c.status, // queued, published
      created_at: c.scheduled_at,
      page_name: c.facebookPage?.name || 'Unknown',
      post_title: c.publication?.post?.title || 'Unknown',
      error: c.last_error
    })),
    ...liveComments.map(c => ({
      id: 'liv_' + c.id,
      real_id: c.id,
      type: 'live',
      message: c.message,
      status: 'published',
      created_at: c.created_time,
      page_name: c.from?.name || 'Người dùng',
      post_title: publications.find(p => p.id === parseInt(scheduleForm.publication_id))?.post?.title || 'Bài viết',
    }))
  ];

  unifiedComments.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

  const filteredComments = unifiedComments.filter(c => {
    if (filter === 'all') return true;
    if (filter === 'draft') return c.status === 'draft'; // mock
    if (filter === 'scheduled') return c.status === 'queued';
    if (filter === 'published') return c.status === 'published';
    if (filter === 'error') return !!c.error;
    return true;
  });

  const getStatusBadge = (c) => {
    if (c.error) return <span className="dn-badge dn-badge-danger">Lỗi</span>;
    if (c.status === 'queued') return <span className="dn-badge dn-badge-warning">Đã lên lịch</span>;
    if (c.status === 'published') return <span className="dn-badge dn-badge-success">Đã đăng</span>;
    return <span className="dn-badge">{c.status}</span>;
  };

  const selectedPageName = publications.find(p => p.id === parseInt(scheduleForm.publication_id))?.facebook_page?.name;

  return (
    <div className="dn-content-wrapper">
      <div className="dn-page-header">
        <div>
          <h1 className="dn-page-title">Bình luận</h1>
          <p className="dn-page-description">Quản lý bình luận của bài viết trên Facebook.</p>
        </div>
        <button className="dn-btn dn-btn-secondary" onClick={handleSyncFacebook} disabled={syncing}>
          {syncing ? 'Đang đồng bộ...' : 'Đồng bộ Facebook'}
        </button>
      </div>

      <div className="dn-filter-tabs">
        <button className={`dn-filter-tab ${filter === 'all' ? 'active' : ''}`} onClick={() => setFilter('all')}>Tất cả {unifiedComments.length}</button>
        <button className={`dn-filter-tab ${filter === 'draft' ? 'active' : ''}`} onClick={() => setFilter('draft')}>Bản nháp 0</button>
        <button className={`dn-filter-tab ${filter === 'scheduled' ? 'active' : ''}`} onClick={() => setFilter('scheduled')}>Đã lên lịch {unifiedComments.filter(c=>c.status==='queued').length}</button>
        <button className={`dn-filter-tab ${filter === 'published' ? 'active' : ''}`} onClick={() => setFilter('published')}>Đã đăng {unifiedComments.filter(c=>c.status==='published').length}</button>
        <button className={`dn-filter-tab ${filter === 'error' ? 'active' : ''}`} onClick={() => setFilter('error')}>Thất bại {unifiedComments.filter(c=>c.error).length}</button>
      </div>

      <div className="dn-grid-2-col">
        {/* Composer */}
        <div className="dn-card dn-composer-card">
          <h3 className="dn-card-title">Viết bình luận</h3>
          
          <div className="dn-form-group">
            <select 
              className="dn-input"
              value={scheduleForm.publication_id}
              onChange={(e) => {
                setScheduleForm({...scheduleForm, publication_id: e.target.value});
                setLiveComments([]); // Reset live comments on post change
              }}
            >
              <option value="">-- Chọn bài viết đã đăng --</option>
              {publications.map(p => (
                <option key={p.id} value={p.id}>
                  {p.post?.title || 'Không có tiêu đề'} - {p.facebook_page?.name}
                </option>
              ))}
            </select>
          </div>

          {selectedPageName && (
            <div className="dn-page-status">
              <span className="dn-dot dn-dot-success"></span>
              {selectedPageName} · Đã kết nối
            </div>
          )}

          <div className="dn-form-group" style={{ marginTop: 'var(--dn-space-4)' }}>
            <textarea 
              className="dn-textarea"
              rows="5"
              value={scheduleForm.message}
              onChange={(e) => setScheduleForm({...scheduleForm, message: e.target.value})}
              placeholder="Nhập nội dung bình luận cho bài viết này..."
            />
            <div className="dn-char-count">{scheduleForm.message.length}/2000 ký tự</div>
          </div>

          {scheduleForm.message && (
            <div className="dn-form-group">
              <label>Thời gian lên lịch</label>
              <input 
                type="datetime-local" 
                className="dn-input"
                value={scheduleForm.scheduled_at}
                onChange={(e) => setScheduleForm({...scheduleForm, scheduled_at: e.target.value})}
              />
            </div>
          )}

          <div className="dn-form-actions" style={{ marginTop: 'var(--dn-space-6)' }}>
            <button className="dn-btn dn-btn-ghost" onClick={handleSaveDraft}>Lưu nháp</button>
            <div className="dn-btn-group-right">
              <button 
                className="dn-btn dn-btn-outline" 
                disabled={!scheduleForm.message.trim() || !scheduleForm.publication_id}
                onClick={handleScheduleSubmit}
              >
                Lên lịch
              </button>
              <button 
                className="dn-btn dn-btn-primary"
                disabled={!scheduleForm.message.trim() || !scheduleForm.publication_id}
                onClick={handleLiveReply}
              >
                Đăng ngay
              </button>
            </div>
          </div>
        </div>

        {/* List */}
        <div className="dn-comments-list">
          {filteredComments.length === 0 ? (
            <div className="dn-empty-state">
              <div className="dn-empty-icon">💬</div>
              <h4>Chưa có bình luận</h4>
              <p>Tạo bình luận đầu tiên hoặc đồng bộ bình luận từ Facebook.</p>
              <button className="dn-btn dn-btn-outline" onClick={() => document.querySelector('.dn-textarea')?.focus()}>
                Viết bình luận
              </button>
            </div>
          ) : (
            <div className="dn-list-items">
              {filteredComments.map(c => (
                <div key={c.id} className="dn-list-item">
                  <div className="dn-item-content">
                    <div className="dn-item-message">{c.message}</div>
                    <div className="dn-item-meta">
                      {moment(c.created_at).format('DD/MM/YYYY · HH:mm')} · {c.page_name}
                    </div>
                    {c.error && <div className="dn-item-error">{c.error}</div>}
                  </div>
                  <div className="dn-item-actions">
                    {getStatusBadge(c)}
                    <div className="dn-menu-dots">
                       {c.status === 'queued' && c.type === 'scheduled' && (
                          <button className="dn-btn-icon dn-text-danger" onClick={() => handleDeleteScheduled(c.real_id)} title="Xóa">
                            🗑️
                          </button>
                       )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
