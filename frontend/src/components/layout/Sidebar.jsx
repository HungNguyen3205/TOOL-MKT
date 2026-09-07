import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';

const Sidebar = ({ isCollapsed, healthStatus }) => {
  const location = useLocation();

  const menuCategories = [
    {
      title: 'TỔNG QUAN',
      items: [
        { name: 'Trang chủ', path: '/dashboard', icon: '🏠' }
      ]
    },
    {
      title: 'NỘI DUNG',
      items: [
        { name: 'Thương hiệu', path: '/brands', icon: '🔖' },
        { name: 'Quản lý bài', path: '/posts', icon: '📝' },
        { name: 'Tạo nội dung AI', path: '/create-content', icon: '✨' },
        { name: 'Chiến dịch', path: '/campaigns', icon: '🚀' },
        { name: 'Cấu Hình Post & Content', path: '/settings', icon: '⚙️' }
      ]
    },
    {
      title: 'FANPAGE',
      items: [
        { name: 'Trang FB', path: '/facebook-pages', icon: '🚩' },
        { name: 'Nhóm Trang', path: '/page-groups', icon: '👥' },
        { name: 'Quản lý Token', path: '/tokens', icon: '🔑' },
        { type: 'button', name: 'Tin nhắn & Bình luận', icon: '💬', color: '#8b5cf6' }
      ]
    },
    {
      title: 'TÀI NGUYÊN',
      items: [
        { name: 'Thư viện', path: '/media', icon: '📁' },
        { name: 'Image Studio', path: '/image-studio', icon: '🖼️' },
        { name: 'Video Studio', path: '/video-studio', icon: '🎥' },
        { name: 'Kho Proxy', path: '/proxy', icon: '🌐' }
      ]
    },
    {
      title: 'BÁO CÁO',
      items: [
        { name: 'Báo cáo', path: '/reports', icon: '📈' },
        { name: 'Queue Monitor', path: '/queue', icon: '⏱️' }
      ]
    },
    {
      title: 'HỆ THỐNG',
      items: [
        { name: 'Cảnh báo lỗi', path: '/alerts', icon: '⚠️', badge: 3 },
        { name: 'Hướng Dẫn', path: '/guide', icon: '📖' },
        { name: 'Gói Hiện Tại', path: '/billing', icon: '🛡️' },
        { name: 'Bảng giá', path: '/pricing', icon: '💳' }
      ]
    }
  ];

  return (
    <aside className={`dn-sidebar ${isCollapsed ? 'collapsed' : ''}`} style={{ backgroundColor: '#111827', color: '#9ca3af', overflowY: 'auto' }}>
      <div className="dn-sidebar-header" style={{ borderBottom: 'none', padding: '20px' }}>
        <div className="dn-nav-icon" style={{ fontSize: '24px', backgroundColor: '#fff', borderRadius: '8px', padding: '4px' }}>♾️</div>
        {!isCollapsed && <span className="dn-sidebar-logo-text" style={{ fontSize: '20px', fontWeight: 'bold', color: '#fff' }}>DANAVA Page</span>}
      </div>
      
      <div className="dn-sidebar-nav" style={{ padding: '0 15px' }}>
        {menuCategories.map((category, idx) => (
          <div key={idx} style={{ marginBottom: '20px' }}>
            {!isCollapsed && (
              <div style={{ fontSize: '11px', fontWeight: 'bold', color: '#6b7280', letterSpacing: '0.05em', marginBottom: '10px', paddingLeft: '10px' }}>
                {category.title}
              </div>
            )}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
              {category.items.map((item, itemIdx) => {
                if (item.type === 'button') {
                  return (
                    <button 
                      key={itemIdx}
                      style={{
                        backgroundColor: item.color,
                        color: '#fff',
                        border: 'none',
                        borderRadius: '8px',
                        padding: '10px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: isCollapsed ? 'center' : 'flex-start',
                        gap: '10px',
                        cursor: 'pointer',
                        fontWeight: '500',
                        marginTop: '5px',
                        width: '100%'
                      }}
                      title={isCollapsed ? item.name : ''}
                    >
                      <span>{item.icon}</span>
                      {!isCollapsed && <span>{item.name}</span>}
                    </button>
                  )
                }

                const isActive = location.pathname.startsWith(item.path);
                return (
                  <Link
                    key={itemIdx}
                    to={item.path}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '10px',
                      padding: '10px',
                      borderRadius: '8px',
                      textDecoration: 'none',
                      color: isActive ? '#fff' : '#9ca3af',
                      backgroundColor: isActive ? 'rgba(255,255,255,0.05)' : 'transparent',
                      transition: 'all 0.2s'
                    }}
                    onMouseOver={e => { if(!isActive) e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.02)' }}
                    onMouseOut={e => { if(!isActive) e.currentTarget.style.backgroundColor = 'transparent' }}
                    title={isCollapsed ? item.name : ''}
                  >
                    <span style={{ 
                      width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center',
                      backgroundColor: isActive ? '#2563eb' : 'rgba(255,255,255,0.05)', 
                      borderRadius: '8px', color: isActive ? '#fff' : '#9ca3af'
                    }}>{item.icon}</span>
                    
                    {!isCollapsed && <span style={{ flex: 1, fontWeight: isActive ? '500' : 'normal' }}>{item.name}</span>}
                    
                    {!isCollapsed && item.badge && (
                      <span style={{ backgroundColor: '#ef4444', color: '#fff', fontSize: '10px', padding: '2px 6px', borderRadius: '10px', fontWeight: 'bold' }}>
                        {item.badge}
                      </span>
                    )}
                  </Link>
                )
              })}
            </div>
          </div>
        ))}
      </div>

      {!isCollapsed && (
        <div style={{ padding: '15px', marginTop: 'auto' }}>
          
          {/* License Box */}
          <div style={{ backgroundColor: '#1f2937', borderRadius: '10px', padding: '12px', display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '15px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: '8px', backgroundColor: 'rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              🛡️
            </div>
            <div>
              <div style={{ color: '#fff', fontWeight: 'bold', fontSize: '13px' }}>Chưa kích hoạt</div>
              <div style={{ color: '#6b7280', fontSize: '12px' }}>—</div>
            </div>
          </div>

          {/* Lang & Theme */}
          <div style={{ display: 'flex', gap: '10px', marginBottom: '15px' }}>
            <button style={{ flex: 1, backgroundColor: '#1f2937', color: '#9ca3af', border: '1px solid #374151', borderRadius: '8px', padding: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px', cursor: 'pointer' }}>
              文 Tiếng Việt
            </button>
            <button style={{ flex: 1, backgroundColor: '#1f2937', color: '#9ca3af', border: '1px solid #374151', borderRadius: '8px', padding: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px', cursor: 'pointer' }}>
              ☀️ Sáng
            </button>
          </div>

          {/* Update Info */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '15px' }}>
            <div style={{ color: '#fff', fontWeight: 'bold', fontSize: '13px' }}>DANAVA Page v1.0.0</div>
            <button style={{ backgroundColor: '#1f2937', color: '#8b5cf6', border: '1px solid #374151', borderRadius: '8px', padding: '6px 10px', fontSize: '12px', display: 'flex', alignItems: 'center', gap: '5px', cursor: 'pointer' }}>
              ↻ Kiểm tra cập nhật
            </button>
          </div>

          {/* Zoom Controls */}
          <div style={{ display: 'flex', gap: '10px', marginBottom: '15px' }}>
            <button style={{ flex: 1, backgroundColor: '#1f2937', color: '#fff', border: '1px solid #374151', borderRadius: '8px', padding: '8px', cursor: 'pointer', fontWeight: 'bold' }}>-</button>
            <button style={{ flex: 2, backgroundColor: '#1f2937', color: '#9ca3af', border: '1px solid #374151', borderRadius: '8px', padding: '8px', cursor: 'default' }}>100%</button>
            <button style={{ flex: 1, backgroundColor: '#1f2937', color: '#fff', border: '1px solid #374151', borderRadius: '8px', padding: '8px', cursor: 'pointer', fontWeight: 'bold' }}>+</button>
          </div>

          {/* Collapse Bottom */}
          <button style={{ width: '100%', backgroundColor: '#1f2937', color: '#9ca3af', border: '1px solid #374151', borderRadius: '8px', padding: '8px', cursor: 'pointer' }}>
            ⌄
          </button>
        </div>
      )}
    </aside>
  );
};

export default Sidebar;
