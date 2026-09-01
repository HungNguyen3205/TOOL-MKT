import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const ResultDisplay = ({ results, onRegenerate, loading, onSave, savedPostId, isSaving }) => {
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

  return (
    <div className="result-display">
      <div className="result-header">
        <h3>Kết quả sinh nội dung</h3>
        <button className="btn-secondary" onClick={onRegenerate} disabled={loading || isSaving}>
          {loading ? 'Đang tạo lại...' : 'Tạo lại nội dung'}
        </button>
      </div>

      <div className="tabs">
        {results.map((v, index) => (
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
          </button>
        ))}
      </div>

      {selectedVersion && (
        <div className="version-card">
          <h4>{selectedVersion.title}</h4>
          <p className="content-text">{selectedVersion.content}</p>
          <p className="cta-text"><strong>CTA:</strong> {selectedVersion.cta}</p>
          <p className="hashtags-text">{selectedVersion.hashtags.join(' ')}</p>

          <div className="card-actions">
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
        </div>
      )}
    </div>
  );
};

export default ResultDisplay;
