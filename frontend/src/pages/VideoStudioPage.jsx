import React, { useState, useEffect } from 'react';
import { fetchPosts, generateVideoDraft, generateVideoFinal, getVideoStatus } from '../api/posts';
import toast from 'react-hot-toast';

const VideoStudioPage = () => {
  const [posts, setPosts] = useState([]);
  const [selectedPostId, setSelectedPostId] = useState('');
  const [selectedPost, setSelectedPost] = useState(null);
  
  const [loading, setLoading] = useState(false);
  const [generatingDraft, setGeneratingDraft] = useState(false);
  const [generatingFinal, setGeneratingFinal] = useState(false);
  
  // Status state
  const [videoStatus, setVideoStatus] = useState('none'); // none, draft_queued, draft_processing, draft_completed, draft_failed, final_queued, final_processing, final_completed, final_failed
  const [draftVideo, setDraftVideo] = useState(null);
  const [finalVideo, setFinalVideo] = useState(null);
  
  // Polling state
  const [pollingInterval, setPollingInterval] = useState(null);
  
  useEffect(() => {
    loadPosts();
    return () => {
      if (pollingInterval) clearInterval(pollingInterval);
    };
  }, []);

  const loadPosts = async () => {
    setLoading(true);
    try {
      const res = await fetchPosts({ limit: 50 });
      const loadedPosts = res.data || [];
      setPosts(loadedPosts);
      
      // Khôi phục trạng thái từ sessionStorage
      const savedPostId = sessionStorage.getItem('videoStudioSelectedPostId');
      if (savedPostId) {
        const post = loadedPosts.find(p => p.id === parseInt(savedPostId));
        if (post) {
          setSelectedPostId(savedPostId);
          setSelectedPost(post);
          fetchStatus(savedPostId);
        }
      }
    } catch (err) {
      toast.error('Lỗi tải danh sách bài viết');
    } finally {
      setLoading(false);
    }
  };

  const fetchStatus = async (postId) => {
    try {
      const res = await getVideoStatus(postId);
      const assets = res.assets || [];
      
      const draft = assets.find(a => a.stage === 'draft' || a.role === 'video_draft');
      const final = assets.find(a => a.stage === 'final' || a.role === 'video_final');
      
      setDraftVideo(draft);
      setFinalVideo(final);
      
      let currentStatus = 'none';
      if (final && ['video_final_queued', 'video_final_processing', 'processing'].includes(final.status)) {
         currentStatus = 'final_processing';
      } else if (draft && ['video_draft_queued', 'video_draft_processing', 'processing'].includes(draft.status)) {
         currentStatus = 'draft_processing';
      } else if (final && (final.status === 'completed' || final.status === 'video_final_ready')) {
         currentStatus = 'final_completed';
      } else if (draft && (draft.status === 'completed' || draft.status === 'video_draft_ready')) {
         currentStatus = 'draft_completed';
      } else if (final && (final.status === 'failed' || final.status === 'video_final_failed')) {
         currentStatus = 'final_failed';
      } else if (draft && (draft.status === 'failed' || draft.status === 'video_draft_failed')) {
         currentStatus = 'draft_failed';
      }
      
      setVideoStatus(currentStatus);
      
      const isProcessing = ['draft_processing', 'final_processing'].includes(currentStatus);
      
      if (isProcessing && !pollingInterval) {
        const interval = setInterval(() => fetchStatus(postId), 10000);
        setPollingInterval(interval);
      } else if (!isProcessing && pollingInterval) {
        clearInterval(pollingInterval);
        setPollingInterval(null);
        setGeneratingDraft(false);
        setGeneratingFinal(false);
      }
    } catch (err) {
      console.error('Error fetching video status:', err);
    }
  };

  const handleSelectPost = (e) => {
    const id = e.target.value;
    setSelectedPostId(id);
    sessionStorage.setItem('videoStudioSelectedPostId', id);
    const post = posts.find(p => p.id === parseInt(id));
    setSelectedPost(post);
    if (pollingInterval) clearInterval(pollingInterval);
    setVideoStatus('none');
    setDraftVideo(null);
    setFinalVideo(null);
    if (id) {
      fetchStatus(id);
    }
  };

  const handleCreateDraft = async () => {
    if (!selectedPostId) return;
    setGeneratingDraft(true);
    try {
      await generateVideoDraft(selectedPostId, []);
      toast.success('Đã gửi yêu cầu tạo video nháp');
      fetchStatus(selectedPostId);
    } catch (err) {
      toast.error(err.message || 'Lỗi khi tạo video nháp');
      setGeneratingDraft(false);
    }
  };

  const handleCreateFinal = async () => {
    if (!selectedPostId) return;
    setGeneratingFinal(true);
    try {
      await generateVideoFinal(selectedPostId, []);
      toast.success('Đã gửi yêu cầu xuất Reel chính thức');
      fetchStatus(selectedPostId);
    } catch (err) {
      toast.error(err.message || 'Lỗi khi tạo video chính thức');
      setGeneratingFinal(false);
    }
  };

  const isDraftProcessing = videoStatus === 'draft_processing' || generatingDraft;
  const isFinalProcessing = videoStatus === 'final_processing' || generatingFinal;

  return (
    <div className="post-list-page" style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}>
      <div className="page-header" style={{ marginBottom: '30px' }}>
        <h2 style={{ fontSize: '2rem', fontWeight: 'bold', background: 'linear-gradient(90deg, #ec4899, #8b5cf6)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>
          🎥 Video Studio
        </h2>
        <p style={{ color: '#aaa', marginTop: '10px' }}>Trung tâm sản xuất Reel, Short Video bằng công nghệ AI tiên tiến.</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '30px' }}>
        {/* Left Column - Controls */}
        <div style={{ backgroundColor: '#1e1e1e', padding: '20px', borderRadius: '12px', border: '1px solid #333' }}>
          <h3 style={{ marginBottom: '20px', borderBottom: '1px solid #333', paddingBottom: '10px' }}>1. Chọn bài viết nguồn</h3>
          
          <div className="form-group">
            <select 
              className="w-full" 
              style={{ padding: '10px', borderRadius: '8px', backgroundColor: '#2d2d2d', color: '#fff', border: '1px solid #444', width: '100%' }}
              value={selectedPostId} 
              onChange={handleSelectPost}
            >
              <option value="">-- Chọn bài viết --</option>
              {posts.map(p => (
                <option key={p.id} value={p.id}>{p.title || `Bài viết #${p.id}`}</option>
              ))}
            </select>
          </div>

          <h3 style={{ marginTop: '30px', marginBottom: '20px', borderBottom: '1px solid #333', paddingBottom: '10px' }}>2. Tiến trình sản xuất</h3>
          
          {/* Draft Button */}
          <button 
            onClick={handleCreateDraft} 
            disabled={!selectedPostId || isDraftProcessing || isFinalProcessing}
            style={{ 
              width: '100%', 
              marginBottom: '15px', 
              padding: '15px', 
              borderRadius: '8px', 
              border: 'none',
              background: isDraftProcessing ? '#555' : 'linear-gradient(135deg, #4b5563, #374151)',
              color: 'white',
              fontWeight: 'bold',
              cursor: (!selectedPostId || isDraftProcessing || isFinalProcessing) ? 'not-allowed' : 'pointer',
              transition: 'all 0.3s'
            }}
          >
            {isDraftProcessing ? '⏳ Đang dựng nháp...' : '🎬 1. Dựng Bản Nháp (Draft)'}
          </button>

          {/* Final Button */}
          <button 
            onClick={handleCreateFinal} 
            disabled={!(draftVideo && (draftVideo.status === 'completed' || draftVideo.status === 'video_draft_ready')) || isDraftProcessing || isFinalProcessing}
            style={{ 
              width: '100%', 
              padding: '15px', 
              borderRadius: '8px', 
              border: 'none',
              background: (!(draftVideo && (draftVideo.status === 'completed' || draftVideo.status === 'video_draft_ready')) || isDraftProcessing || isFinalProcessing) ? '#555' : 'linear-gradient(135deg, #ec4899, #8b5cf6)',
              color: 'white',
              fontWeight: 'bold',
              cursor: (!(draftVideo && (draftVideo.status === 'completed' || draftVideo.status === 'video_draft_ready')) || isDraftProcessing || isFinalProcessing) ? 'not-allowed' : 'pointer',
              transition: 'all 0.3s',
              boxShadow: (!(draftVideo && (draftVideo.status === 'completed' || draftVideo.status === 'video_draft_ready')) || isDraftProcessing || isFinalProcessing) ? 'none' : '0 4px 15px rgba(236, 72, 153, 0.4)'
            }}
          >
            {isFinalProcessing ? '⏳ Đang render...' : '🚀 2. Xuất Reel Chính Thức'}
          </button>
        </div>

        {/* Right Column - Preview */}
        <div style={{ backgroundColor: '#1e1e1e', padding: '20px', borderRadius: '12px', border: '1px solid #333', minHeight: '500px', display: 'flex', flexDirection: 'column' }}>
          <h3 style={{ marginBottom: '20px', borderBottom: '1px solid #333', paddingBottom: '10px' }}>Màn hình Preview</h3>
          
          <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
            {/* Draft Preview */}
            <div style={{ backgroundColor: '#121212', borderRadius: '8px', border: '1px dashed #444', overflow: 'hidden', padding: '15px', display: 'flex', flexDirection: 'column' }}>
              <h4 style={{ color: '#aaa', textAlign: 'center', marginBottom: '15px' }}>Bản Nháp (Draft)</h4>
              <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                {isDraftProcessing ? (
                  <div style={{ textAlign: 'center', color: '#9ca3af' }}>
                    <div style={{ display: 'inline-block', width: '30px', height: '30px', border: '2px solid rgba(156, 163, 175, 0.3)', borderRadius: '50%', borderTopColor: '#9ca3af', animation: 'spin 1s ease-in-out infinite', marginBottom: '10px' }} />
                    <p>Đang dựng nháp...</p>
                  </div>
                ) : draftVideo && (draftVideo.status === 'completed' || draftVideo.status === 'video_draft_ready') ? (
                  <video src={draftVideo.path} controls style={{ width: '100%', maxHeight: '400px', borderRadius: '4px' }}></video>
                ) : draftVideo && (draftVideo.status === 'failed' || draftVideo.status === 'video_draft_failed') ? (
                  <div style={{ textAlign: 'center' }}>
                    <p style={{ color: '#ef4444', marginBottom: '10px' }}>❌ Lỗi dựng nháp</p>
                    <p style={{ color: '#888', fontSize: '12px' }}>{draftVideo.error_message || 'Có lỗi xảy ra'}</p>
                  </div>
                ) : (
                  <p style={{ color: '#555', textAlign: 'center' }}>Chưa có bản nháp</p>
                )}
              </div>
            </div>

            {/* Final Preview */}
            <div style={{ backgroundColor: '#121212', borderRadius: '8px', border: '1px dashed #444', overflow: 'hidden', padding: '15px', display: 'flex', flexDirection: 'column' }}>
              <h4 style={{ color: '#ec4899', textAlign: 'center', marginBottom: '15px', fontWeight: 'bold' }}>Bản Chính Thức (Reel)</h4>
              <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                {isFinalProcessing ? (
                  <div style={{ textAlign: 'center', color: '#ec4899' }}>
                    <div style={{ display: 'inline-block', width: '30px', height: '30px', border: '2px solid rgba(236, 72, 153, 0.3)', borderRadius: '50%', borderTopColor: '#ec4899', animation: 'spin 1s ease-in-out infinite', marginBottom: '10px' }} />
                    <p>Đang xuất video chất lượng cao...</p>
                  </div>
                ) : finalVideo && (finalVideo.status === 'completed' || finalVideo.status === 'video_final_ready') ? (
                  <video src={finalVideo.path} controls style={{ width: '100%', maxHeight: '400px', borderRadius: '4px' }}></video>
                ) : finalVideo && (finalVideo.status === 'failed' || finalVideo.status === 'video_final_failed') ? (
                  <div style={{ textAlign: 'center' }}>
                    <p style={{ color: '#ef4444', marginBottom: '10px' }}>❌ Lỗi xuất video</p>
                    <p style={{ color: '#888', fontSize: '12px' }}>{finalVideo.error_message || 'Có lỗi xảy ra'}</p>
                  </div>
                ) : (
                  <p style={{ color: '#555', textAlign: 'center' }}>Chưa có bản chính thức</p>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default VideoStudioPage;
