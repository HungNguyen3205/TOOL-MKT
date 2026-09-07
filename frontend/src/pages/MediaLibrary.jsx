import React, { useState } from 'react';

const MediaLibrary = () => {
  const [currentFolder, setCurrentFolder] = useState('My Drive');
  
  // Mock Data
  const folders = [
    { id: 1, name: 'My Drive', icon: '📁', isOpen: true, children: [
      { id: 2, name: 'Brand DANAVA', icon: '📁', isOpen: false, children: [] },
      { id: 3, name: 'Brand GLG', icon: '📁', isOpen: false, children: [] },
      { id: 4, name: 'Ảnh AI Tạo', icon: '📁', isOpen: false, children: [] },
      { id: 5, name: 'Video Uploads', icon: '📁', isOpen: false, children: [] }
    ]},
  ];

  const files = [
    { id: 101, name: 'yoga-post-1.png', type: 'image', size: '2.4 MB', date: '07/09/2026' },
    { id: 102, name: 'danava-banner.jpg', type: 'image', size: '5.1 MB', date: '06/09/2026' },
    { id: 103, name: 'promo-video.mp4', type: 'video', size: '45 MB', date: '05/09/2026' },
    { id: 104, name: 'ai-generated-avatar.png', type: 'image', size: '1.2 MB', date: '04/09/2026' },
  ];

  const renderTree = (nodes, depth = 0) => {
    return nodes.map(node => (
      <div key={node.id}>
        <div 
          onClick={() => setCurrentFolder(node.name)}
          style={{ 
            display: 'flex', alignItems: 'center', gap: '8px', 
            padding: `8px 8px 8px ${depth * 15 + 8}px`, 
            cursor: 'pointer',
            backgroundColor: currentFolder === node.name ? 'var(--dn-bg-surface-hover)' : 'transparent',
            borderRadius: '4px',
            color: currentFolder === node.name ? 'var(--dn-color-primary)' : 'var(--dn-text-secondary)',
            fontWeight: currentFolder === node.name ? '500' : 'normal'
          }}
        >
          <span>{node.children && node.children.length > 0 ? (node.isOpen ? '▼' : '▶') : ' '}</span>
          <span>{node.icon}</span>
          <span>{node.name}</span>
        </div>
        {node.isOpen && node.children && renderTree(node.children, depth + 1)}
      </div>
    ));
  };

  return (
    <div className="dn-media-library" style={{ display: 'flex', gap: 'var(--dn-space-6)', height: 'calc(100vh - 120px)' }}>
      
      {/* Left Sidebar - Folder Tree */}
      <div style={{ width: '250px', backgroundColor: 'var(--dn-bg-surface)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-lg)', padding: 'var(--dn-space-4)', display: 'flex', flexDirection: 'column' }}>
        <button className="dn-btn dn-btn-primary" style={{ padding: '12px', fontSize: '1rem', marginBottom: 'var(--dn-space-6)', boxShadow: 'var(--dn-shadow-md)' }}>
          + Upload Mới
        </button>
        <div style={{ flex: 1, overflowY: 'auto' }}>
          {renderTree(folders)}
        </div>
        <div style={{ marginTop: 'auto', paddingTop: 'var(--dn-space-4)', borderTop: '1px solid var(--dn-border-color)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--dn-text-secondary)', fontSize: 'var(--dn-text-xs)', marginBottom: '4px' }}>
            <span>Storage</span>
            <span>45%</span>
          </div>
          <div style={{ height: '6px', backgroundColor: 'var(--dn-bg-surface-hover)', borderRadius: '3px' }}>
            <div style={{ height: '100%', width: '45%', backgroundColor: 'var(--dn-color-primary)', borderRadius: '3px' }}></div>
          </div>
          <div style={{ color: 'var(--dn-text-tertiary)', fontSize: 'var(--dn-text-xs)', marginTop: '4px' }}>
            4.5 GB of 10 GB used
          </div>
        </div>
      </div>

      {/* Main Content Area */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
        {/* Breadcrumb & Top Bar */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'var(--dn-space-4)', backgroundColor: 'var(--dn-bg-surface)', padding: 'var(--dn-space-4)', borderRadius: 'var(--dn-radius-lg)', border: '1px solid var(--dn-border-color)' }}>
          <div style={{ fontSize: 'var(--dn-text-lg)', color: 'var(--dn-text-primary)' }}>
            <span style={{ color: 'var(--dn-text-tertiary)', cursor: 'pointer' }}>Media Library</span> 
            <span style={{ margin: '0 8px', color: 'var(--dn-text-tertiary)' }}>/</span> 
            <span style={{ fontWeight: '500' }}>{currentFolder}</span>
          </div>
          <div style={{ display: 'flex', gap: 'var(--dn-space-2)' }}>
            <button className="dn-btn" style={{ border: '1px solid var(--dn-border-color)' }}>⊞ Lưới</button>
            <button className="dn-btn" style={{ border: '1px solid var(--dn-border-color)' }}>☰ Danh sách</button>
          </div>
        </div>

        {/* Files Grid */}
        <div style={{ flex: 1, backgroundColor: 'var(--dn-bg-surface)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-lg)', padding: 'var(--dn-space-6)', overflowY: 'auto' }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 'var(--dn-space-4)' }}>
            
            {files.map(file => (
              <div key={file.id} style={{ 
                border: '1px solid var(--dn-border-color)', 
                borderRadius: 'var(--dn-radius-md)', 
                overflow: 'hidden',
                backgroundColor: 'var(--dn-bg-app)',
                cursor: 'pointer',
                transition: 'transform var(--dn-transition-fast)'
              }}
              onMouseOver={e => e.currentTarget.style.transform = 'translateY(-2px)'}
              onMouseOut={e => e.currentTarget.style.transform = 'none'}
              >
                <div style={{ height: '120px', display: 'flex', justifyContent: 'center', alignItems: 'center', backgroundColor: 'var(--dn-bg-surface-hover)', fontSize: '3rem', color: 'var(--dn-text-tertiary)' }}>
                  {file.type === 'image' ? '🖼️' : '🎥'}
                </div>
                <div style={{ padding: 'var(--dn-space-3)' }}>
                  <div style={{ fontSize: 'var(--dn-text-sm)', fontWeight: '500', color: 'var(--dn-text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {file.name}
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 'var(--dn-space-1)', fontSize: 'var(--dn-text-xs)', color: 'var(--dn-text-secondary)' }}>
                    <span>{file.size}</span>
                    <span>{file.date}</span>
                  </div>
                </div>
              </div>
            ))}
            
            {/* Empty space filler for dropzone */}
            <div style={{ 
              border: '2px dashed var(--dn-border-color)', 
              borderRadius: 'var(--dn-radius-md)', 
              height: '180px',
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'center',
              alignItems: 'center',
              color: 'var(--dn-text-tertiary)',
              cursor: 'pointer'
            }}>
              <span style={{ fontSize: '2rem', marginBottom: '8px' }}>☁️</span>
              <span style={{ fontSize: 'var(--dn-text-sm)' }}>Kéo thả file vào đây</span>
            </div>

          </div>
        </div>

      </div>
    </div>
  );
};

export default MediaLibrary;
