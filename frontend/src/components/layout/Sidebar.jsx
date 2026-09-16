import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import logoImg from '../../images/logo.png';

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
        { name: 'Nhập lịch nội dung', path: '/posts/import', icon: '📥' },
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
        { name: 'Tin nhắn & Bình luận', path: '/comments', icon: '💬', color: 'var(--dn-color-primary)' }
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
    <aside className={`dn-sidebar ${isCollapsed ? 'collapsed' : ''}`} style={{ backgroundColor: 'var(--dn-bg-app)', color: 'var(--dn-text-secondary)', overflowY: 'auto', display: 'flex', flexDirection: 'column' }}>
      <div className="dn-sidebar-header" style={{ borderBottom: 'none', padding: 'var(--dn-space-5)' }}>
        <div className="dn-nav-icon" style={{ backgroundColor: 'transparent', borderRadius: 'var(--dn-radius-sm)', padding: '0', display: 'flex', alignItems: 'center' }}>
          <img src={logoImg} alt="DANAVA Logo" style={{ width: '40px', height: '40px', objectFit: 'contain' }} />
        </div>
        {!isCollapsed && <span className="dn-sidebar-logo-text" style={{ fontSize: '20px', fontWeight: 'bold', color: 'var(--dn-text-primary)' }}>DANAVA Page</span>}
      </div>
      
      <div className="dn-sidebar-nav" style={{ padding: '0 var(--dn-space-4)' }}>
        {menuCategories.map((category, idx) => (
          <div key={idx} style={{ marginBottom: 'var(--dn-space-5)' }}>
            {!isCollapsed && (
              <div style={{ fontSize: '11px', fontWeight: 'bold', color: 'var(--dn-text-muted)', letterSpacing: '0.05em', marginBottom: 'var(--dn-space-2)', paddingLeft: '10px' }}>
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
                        borderRadius: 'var(--dn-radius-sm)',
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
                      borderRadius: 'var(--dn-radius-sm)',
                      textDecoration: 'none',
                      color: isActive ? 'var(--dn-text-primary)' : 'var(--dn-text-secondary)',
                      backgroundColor: isActive ? 'var(--dn-color-primary-light)' : 'transparent',
                      transition: 'all 0.2s'
                    }}
                    onMouseOver={e => { if(!isActive) e.currentTarget.style.backgroundColor = 'var(--dn-bg-surface-hover)' }}
                    onMouseOut={e => { if(!isActive) e.currentTarget.style.backgroundColor = 'transparent' }}
                    title={isCollapsed ? item.name : ''}
                  >
                    <span style={{ 
                      width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center',
                      backgroundColor: isActive ? 'var(--dn-color-primary)' : 'var(--dn-bg-surface)', 
                      borderRadius: 'var(--dn-radius-sm)', color: isActive ? '#fff' : 'var(--dn-text-secondary)'
                    }}>{item.icon}</span>
                    
                    {!isCollapsed && <span style={{ flex: 1, fontWeight: isActive ? '500' : 'normal' }}>{item.name}</span>}
                    
                    {!isCollapsed && item.badge && (
                      <span style={{ backgroundColor: 'var(--dn-color-danger)', color: '#fff', fontSize: '10px', padding: '2px 6px', borderRadius: '10px', fontWeight: 'bold' }}>
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
          <div style={{ backgroundColor: 'var(--dn-bg-surface)', borderRadius: 'var(--dn-radius-sm)', padding: '12px', display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '15px' }}>
            <div style={{ width: '36px', height: '36px', borderRadius: 'var(--dn-radius-sm)', backgroundColor: 'var(--dn-bg-surface-hover)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              🛡️
            </div>
            <div>
              <div style={{ color: 'var(--dn-text-primary)', fontWeight: 'bold', fontSize: '13px' }}>Chưa kích hoạt</div>
              <div style={{ color: 'var(--dn-text-muted)', fontSize: '12px' }}>—</div>
            </div>
          </div>

          {/* Lang & Theme */}
          <div style={{ display: 'flex', gap: '10px', marginBottom: '15px' }}>
            <button style={{ flex: 1, backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-text-secondary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px', cursor: 'pointer' }}>
              文 Tiếng Việt
            </button>
            <button style={{ flex: 1, backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-text-secondary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px', cursor: 'pointer' }}>
              ☀️ Sáng
            </button>
          </div>

          {/* Update Info */}
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '15px' }}>
            <div style={{ color: 'var(--dn-text-primary)', fontWeight: 'bold', fontSize: '13px' }}>DANAVA Page v1.0.0</div>
            <button style={{ backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-color-primary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '6px 10px', fontSize: '12px', display: 'flex', alignItems: 'center', gap: '5px', cursor: 'pointer' }}>
              ↻ Kiểm tra cập nhật
            </button>
          </div>

          {/* Zoom Controls */}
          <div style={{ display: 'flex', gap: '10px', marginBottom: '15px' }}>
            <button style={{ flex: 1, backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-text-primary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '8px', cursor: 'pointer', fontWeight: 'bold' }}>-</button>
            <button style={{ flex: 2, backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-text-secondary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '8px', cursor: 'default' }}>100%</button>
            <button style={{ flex: 1, backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-text-primary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '8px', cursor: 'pointer', fontWeight: 'bold' }}>+</button>
          </div>

          {/* Collapse Bottom */}
          <button style={{ width: '100%', backgroundColor: 'var(--dn-bg-surface)', color: 'var(--dn-text-secondary)', border: '1px solid var(--dn-border-color)', borderRadius: 'var(--dn-radius-sm)', padding: '8px', cursor: 'pointer' }}>
            ⌄
          </button>
        </div>
      )}
    </aside>
  );
};

export default Sidebar;
