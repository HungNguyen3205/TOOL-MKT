import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const ResultDisplay = ({ results, metadata, onRegenerate, loading, onSave, savedPostId, isSaving }) => {
  const [selectedVersionIndex, setSelectedVersionIndex] = useState(0);
  const [copySuccess, setCopySuccess] = useState('');
  const navigate = useNavigate();

  if (!results || results.length === 0) return null;

  const handleCopy = async (version) => {
    const textToCopy = `${version.title}\n\n${version.content}\n\n${version.cta || ''}\n\n${version.hashtags.join(' ')}`;
    try {
      await navigator.clipboard.writeText(textToCopy);
      setCopySuccess('Đã sao chép nội dung');
      setTimeout(() => setCopySuccess(''), 3000);
    } catch (err) {
      setCopySuccess('Không thể sao chép. Vui lòng copy thủ công.');
      setTimeout(() => setCopySuccess(''), 3000);
    }
  };

  const selectedVersion = results[selectedVersionIndex];

  const getStatusColor = (status) => {
    switch(status) {
      case 'passed': return '#4caf50'; // Green
      case 'warning': return '#ff9800'; // Orange
      case 'failed': return '#f44336'; // Red
      default: return '#757575'; // Grey
    }
  };

  const statusLabel = {
    passed: 'Đạt yêu cầu',
    warning: 'Cần kiểm tra',
    failed: 'Không đạt'
  };

  return (
    <div className="result-display">
      <div className="result-header">
        <h3>Kết quả sinh nội dung</h3>
        <button className="btn-secondary" onClick={onRegenerate} disabled={loading || isSaving}>
          {loading ? 'Đang tạo lại...' : 'Tạo lại nội dung'}
        </button>
      </div>

      <div className="tabs">
        {results.map((v, index) => {
          const score = v.quality?.score ?? '?';
          const status = v.quality?.status ?? 'unknown';
          return (
            <button
              key={index}
              className={`tab-button ${index === selectedVersionIndex ? 'active' : ''}`}
              onClick={() => {
                if (savedPostId && index !== selectedVersionIndex) {
                   if(!window.confirm('Bạn đã lưu một phiên bản khác. Đổi phiên bản sẽ không tự động cập nhật bản nháp đã lưu trừ khi bạn bấm Lưu. Tiếp tục?')) return;
                }
                setSelectedVersionIndex(index);
              }}
            >
              Phiên bản {index + 1}
              <span style={{ 
                marginLeft: '8px', 
                backgroundColor: getStatusColor(status), 
                color: 'white', 
                padding: '2px 8px', 
                borderRadius: '12px', 
                fontSize: '0.75rem' 
              }}>
                Chất lượng: {score}/100
              </span>
            </button>
          )
        })}
      </div>

      {selectedVersion && (
        <div className="version-card">
          {selectedVersion.quality && (
            <div style={{ marginBottom: '15px', padding: '10px', backgroundColor: '#f9f9f9', borderRadius: '8px', borderLeft: `4px solid ${getStatusColor(selectedVersion.quality.status)}` }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <strong>Đánh giá AI: {statusLabel[selectedVersion.quality.status]} ({selectedVersion.quality.score} điểm)</strong>
                <span style={{ fontSize: '0.85rem', color: '#666' }}>Emoji: {selectedVersion.quality.emoji_count}</span>
              </div>
              
              {selectedVersion.quality.errors.length > 0 && (
                <div style={{ color: '#d32f2f', fontSize: '0.9rem', marginTop: '8px' }}>
                  <strong>Lỗi nghiêm trọng:</strong>
                  <ul style={{ margin: '4px 0', paddingLeft: '20px' }}>
                    {selectedVersion.quality.errors.map((e, i) => <li key={i}>{e}</li>)}
                  </ul>
                </div>
              )}
              
              {selectedVersion.quality.warnings.length > 0 && (
                <div style={{ color: '#f57c00', fontSize: '0.9rem', marginTop: '8px' }}>
                  <strong>Cảnh báo:</strong>
                  <ul style={{ margin: '4px 0', paddingLeft: '20px' }}>
                    {selectedVersion.quality.warnings.map((w, i) => <li key={i}>{w}</li>)}
                  </ul>
                </div>
              )}

              {selectedVersion.quality.missing_keywords && selectedVersion.quality.missing_keywords.length > 0 && (
                <div style={{ color: '#d32f2f', fontSize: '0.85rem', marginTop: '4px' }}>
                  Thiếu từ khóa: {selectedVersion.quality.missing_keywords.join(', ')}
                </div>
              )}
              
              {selectedVersion.quality.prohibited_terms_found && selectedVersion.quality.prohibited_terms_found.length > 0 && (
                <div style={{ color: '#d32f2f', fontSize: '0.85rem', marginTop: '4px' }}>
                  Chứa từ cấm: {selectedVersion.quality.prohibited_terms_found.join(', ')}
                </div>
              )}

              {selectedVersion.quality.suspicious_claims && selectedVersion.quality.suspicious_claims.length > 0 && (
                <div style={{ color: '#f57c00', fontSize: '0.85rem', marginTop: '4px' }}>
                  Cam kết/Số liệu cần kiểm chứng: {selectedVersion.quality.suspicious_claims.join(', ')}
                </div>
              )}
            </div>
          )}

          <h4>{selectedVersion.title}</h4>
          <p className="content-text">{selectedVersion.content}</p>
          <p className="cta-text"><strong>CTA:</strong> {selectedVersion.cta}</p>
          <p className="hashtags-text">{selectedVersion.hashtags.join(' ')}</p>

          <div className="card-actions" style={{ marginTop: '20px', display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
            <button className="btn-secondary" onClick={() => handleCopy(selectedVersion)}>
              Sao chép
            </button>
            
            {!savedPostId ? (
              <button 
                className="btn-primary" 
                onClick={() => onSave(selectedVersion, selectedVersionIndex + 1)}
                disabled={isSaving}
              >
                {isSaving ? 'Đang lưu...' : 'Lưu bản nháp'}
              </button>
            ) : (
              <>
                <button 
                  className="btn-primary" 
                  onClick={() => onSave(selectedVersion, selectedVersionIndex + 1)}
                  disabled={isSaving}
                >
                  {isSaving ? 'Đang cập nhật...' : 'Cập nhật bản nháp'}
                </button>
                <button 
                  className="btn-success" 
                  onClick={() => navigate(`/posts/${savedPostId}/edit`)}
                >
                  Mở trong trình chỉnh sửa
                </button>
              </>
            )}
            
            {copySuccess && <span className="copy-success">{copySuccess}</span>}
          </div>
          <div style={{ fontSize: '0.8rem', color: '#888', marginTop: '10px' }}>
            <em>Lưu ý: Điểm chất lượng chỉ là kiểm tra tự động. Phiên bản "Không đạt" vẫn có thể được lưu nháp để bạn tự chỉnh sửa lại.</em>
          </div>
        </div>
      )}

      {metadata && (
        <div style={{ marginTop: '20px', padding: '10px', fontSize: '0.8rem', color: '#666', borderTop: '1px solid #eee' }}>
          <strong>Thông tin hệ thống AI:</strong>
          <ul style={{ margin: '4px 0', paddingLeft: '20px' }}>
            <li>Provider: {metadata.provider} ({metadata.model})</li>
            <li>Thời gian xử lý: {metadata.duration_ms} ms</li>
            {metadata.total_tokens !== null && <li>Token sử dụng: {metadata.total_tokens} (Vào: {metadata.input_tokens}, Ra: {metadata.output_tokens})</li>}
            {metadata.estimated_cost !== null && <li>Chi phí ước tính: ${metadata.estimated_cost}</li>}
            {metadata.retried && <li>Hệ thống đã tự động thử lại 1 lần do kết quả ban đầu kém chất lượng.</li>}
          </ul>
        </div>
      )}
    </div>
  );
};

export default ResultDisplay;
