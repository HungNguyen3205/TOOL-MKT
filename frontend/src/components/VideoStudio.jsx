import React, { useState, useEffect } from 'react';
import { generateVideoDraft, generateVideoFinal, getVideoStatus } from '../api/posts';

const VideoStudio = ({ post, onVideoStatusChanged }) => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [videoAssets, setVideoAssets] = useState([]);
  const [providerCookie, setProviderCookie] = useState('');

  const fetchStatus = async () => {
    try {
      const data = await getVideoStatus(post.id);
      setVideoAssets(data.assets || []);
      if (onVideoStatusChanged) {
        onVideoStatusChanged(data.assets || []);
      }
    } catch (err) {
      console.error('Failed to fetch video status', err);
    }
  };

  useEffect(() => {
    fetchStatus();
    // Poll every 10 seconds if any video is processing
    const interval = setInterval(() => {
      const isProcessing = videoAssets.some(a => 
        a.status === 'video_draft_processing' || 
        a.status === 'video_final_processing' || 
        a.status === 'processing'
      );
      if (isProcessing) {
        fetchStatus();
      }
    }, 10000);

    return () => clearInterval(interval);
  }, [post.id, videoAssets]);

  const handleCreateDraft = async () => {
    setLoading(true);
    setError(null);
    try {
      await generateVideoDraft(post.id, []);
      await fetchStatus();
    } catch (err) {
      setError(err.message || 'Failed to create video draft');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateFinal = async () => {
    const confirmFinal = window.confirm("Thao tác này sẽ gọi API tạo video và có thể tiêu tốn credit. Bạn có muốn tiếp tục?");
    if (!confirmFinal) return;

    setLoading(true);
    setError(null);
    try {
      await generateVideoFinal(post.id, []);
      await fetchStatus();
    } catch (err) {
      setError(err.message || 'Failed to create final video');
    } finally {
      setLoading(false);
    }
  };

  const draftVideo = videoAssets.find(a => a.stage === 'draft' && a.status === 'video_draft_ready');
  const finalVideo = videoAssets.find(a => (a.stage === 'final' || a.stage === 'branded') && (a.status === 'video_final_ready' || a.status === 'ready'));
  const isProcessing = videoAssets.some(a => 
    a.status === 'video_draft_processing' || 
    a.status === 'video_final_processing' || 
    a.status === 'processing'
  );

  if (import.meta.env.VITE_VIDEO_GENERATION_ENABLED !== 'true') {
    return null;
  }

  return (
    <div className="bg-white p-6 rounded-xl border border-gray-100 shadow-sm mt-6">
      <h3 className="text-lg font-bold text-gray-900 mb-4">Video Studio (Beta)</h3>
      
      {error && (
        <div className="bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm">
          {error}
        </div>
      )}

      <div className="flex gap-4">
        {/* Draft Section */}
        <div className="flex-1 border rounded-lg p-4 bg-gray-50">
          <h4 className="font-semibold text-gray-800 mb-2">Video Nháp</h4>
          {draftVideo ? (
            <div className="mb-4">
              <video src={draftVideo.path} controls className="w-full rounded-lg" style={{ maxHeight: '300px' }}></video>
            </div>
          ) : (
            <p className="text-sm text-gray-500 mb-4">Chưa có video nháp. Vui lòng tạo để xem trước bố cục.</p>
          )}
          
          <button 
            onClick={handleCreateDraft} 
            disabled={loading || isProcessing}
            className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg disabled:opacity-50"
          >
            {isProcessing ? 'Đang xử lý...' : 'Tạo Video Nháp'}
          </button>
        </div>

        {/* Final Section */}
        <div className="flex-1 border rounded-lg p-4 bg-gray-50">
          <h4 className="font-semibold text-gray-800 mb-2">Video Chính Thức (Reel)</h4>
          {finalVideo ? (
            <div className="mb-4">
              <video src={finalVideo.path} controls className="w-full rounded-lg" style={{ maxHeight: '300px' }}></video>
              <div className="mt-2 text-xs text-green-600 font-medium">Đã chèn logo & xuất bản thành công!</div>
            </div>
          ) : (
            <p className="text-sm text-gray-500 mb-4">Sau khi duyệt video nháp, bạn có thể xuất Reel chính thức.</p>
          )}
          
          <button 
            onClick={handleCreateFinal} 
            disabled={loading || isProcessing || !draftVideo}
            className="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg disabled:opacity-50"
          >
            {isProcessing ? 'Đang xử lý...' : 'Duyệt & Xuất Reel (Tốn Credit)'}
          </button>
        </div>
      </div>
    </div>
  );
};

export default VideoStudio;
