import React from 'react';

const FacebookPreview = ({ title, content, cta, hashtags, imageUrl }) => {
  return (
    <div className="fb-preview">
      <div className="fb-preview-header">
        <div className="fb-avatar"></div>
        <div className="fb-user-info">
          <strong>Tên Facebook Page</strong>
          <span>Vừa xong · 🌎</span>
        </div>
      </div>
      
      <div className="fb-preview-content">
        {title && <h4>{title}</h4>}
        
        {content && (
          <p className="content-text">
            {content}
          </p>
        )}
        
        {cta && <p className="cta-text">{cta}</p>}
        
        {hashtags && hashtags.length > 0 && (
          <p className="hashtags-text">
            {hashtags.join(' ')}
          </p>
        )}
      </div>
      
      {imageUrl ? (
        <div className="fb-preview-image" style={{ width: '100%' }}>
          <img src={imageUrl} alt="Post preview" style={{ width: '100%', display: 'block' }} />
        </div>
      ) : (
        <div className="fb-preview-image-placeholder">
          <span>Ảnh bài viết (nếu có)</span>
        </div>
      )}
      
      <div className="fb-preview-footer">
        <button>👍 Thích</button>
        <button>💬 Bình luận</button>
        <button>🔗 Chia sẻ</button>
      </div>
    </div>
  );
};

export default FacebookPreview;
