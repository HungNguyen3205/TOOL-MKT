import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { previewImport, confirmImport, verifyDriveFolder } from '../api/imports';
import { fetchBrands } from '../api/brands';
import { getConnectedPages } from '../api/facebook';
import toast from 'react-hot-toast';

const PostImport = () => {
  const navigate = useNavigate();
  const [step, setStep] = useState(1);
  const [loading, setLoading] = useState(false);
  
  // Step 1 State
  const [file, setFile] = useState(null);
  const [brands, setBrands] = useState([]);
  const [pages, setPages] = useState([]);
  
  const [options, setOptions] = useState({
    workspace_id: 1, // Default
    brand_id: '',
    facebook_page_id: '',
    schedule_mode: 'excel', // 'excel' or 'tool'
    timezone: 'Asia/Ho_Chi_Minh',
    start_date: new Date().toISOString().split('T')[0],
    frequency: '1',
    times: ['08:00'],
    google_drive_folder_url: '',
    google_drive_folder_id: ''
  });

  const [driveStatus, setDriveStatus] = useState('idle'); // idle, loading, success, error
  const [driveStats, setDriveStats] = useState(null);
  const [rememberDrive, setRememberDrive] = useState(false);

  const [saveAsDraft, setSaveAsDraft] = useState(false);

  // Step 2 State
  const [previewData, setPreviewData] = useState(null);
  const [rows, setRows] = useState([]);
  
  useEffect(() => {
    loadDependencies();
  }, []);

  const loadDependencies = async () => {
    try {
      const [brandsRes, pagesRes] = await Promise.all([
        fetchBrands(),
        getConnectedPages()
      ]);
      setBrands(brandsRes.data || []);
      setPages(pagesRes.data || []);
    } catch (err) {
      toast.error('Lỗi tải dữ liệu cơ sở');
    }
  };

  const handleFileChange = (e) => {
    if (e.target.files && e.target.files[0]) {
      setFile(e.target.files[0]);
    }
  };

  const handleFrequencyChange = (e) => {
    const freq = parseInt(e.target.value);
    const newTimes = [...options.times];
    
    // Adjust array size
    if (freq > newTimes.length) {
      for (let i = newTimes.length; i < freq; i++) {
        newTimes.push(i === 1 ? '14:30' : '19:00'); // defaults for 2nd and 3rd
      }
    } else {
      newTimes.splice(freq);
    }
    
    setOptions({ ...options, frequency: freq.toString(), times: newTimes });
  };

  const handleTimeChange = (index, value) => {
    const newTimes = [...options.times];
    newTimes[index] = value;
    setOptions({ ...options, times: newTimes });
  };

  const handlePreview = async () => {
    setLoading(true);
    try {
      if (!file) return toast.error('Vui lòng chọn file Excel hoặc CSV');
      
      const formData = new FormData();
      formData.append('file', file);
      
      Object.keys(options).forEach(key => {
        if (Array.isArray(options[key])) {
          options[key].forEach(val => formData.append(`${key}[]`, val));
        } else {
          formData.append(key, options[key] || '');
        }
      });
      formData.append('remember_drive', rememberDrive ? '1' : '0');
      
      const res = await previewImport(formData);
      setPreviewData(res.data);
      setRows(res.data.rows);
      setStep(2);
    } catch (err) {
      toast.error(err.message || 'Lỗi đọc dữ liệu');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyDrive = async () => {
    if (!options.google_drive_folder_url) {
      return toast.error('Vui lòng nhập link thư mục Google Drive');
    }
    
    setDriveStatus('loading');
    try {
      const res = await verifyDriveFolder(options.google_drive_folder_url);
      setDriveStats(res.data);
      setOptions({ ...options, google_drive_folder_id: res.data.folder_id });
      setDriveStatus('success');
      toast.success('Kết nối Google Drive thành công');
    } catch (err) {
      setDriveStatus('error');
      setDriveStats(null);
      setOptions({ ...options, google_drive_folder_id: '' });
      toast.error(err.message || 'Lỗi kết nối thư mục');
    }
  };

  const handleBrandChange = (e) => {
    const brandId = e.target.value;
    const selectedBrand = brands.find(b => b.id == brandId);
    
    setOptions({ 
      ...options, 
      brand_id: brandId,
      google_drive_folder_url: selectedBrand?.google_drive_folder_url || '',
      google_drive_folder_id: selectedBrand?.google_drive_folder_id || ''
    });
    
    if (selectedBrand?.google_drive_folder_url) {
      setDriveStatus('idle'); // reset to encourage re-verify if needed, or we could assume it's good
    }
  };

  const handleConfirm = async () => {
    setLoading(true);
    try {
      const action = saveAsDraft ? 'save_draft' : 'skip';
      const res = await confirmImport(previewData.batch_id, rows, action);
      toast.success(res.message);
      setStep(3);
    } catch (err) {
      toast.error(err.message || 'Lỗi lưu dữ liệu');
    } finally {
      setLoading(false);
    }
  };

  const toggleExclude = (index) => {
    const newRows = [...rows];
    newRows[index].exclude = !newRows[index].exclude;
    setRows(newRows);
  };

  const handleRowChange = (index, field, value) => {
    const newRows = [...rows];
    newRows[index][field] = value;
    
    // Dynamic Drive Image matching
    if (field === 'image_key' && previewData?.drive_images_map) {
      if (!value) {
         newRows[index].google_drive_file = null;
         newRows[index].google_drive_error = null;
      } else {
         const map = previewData.drive_images_map;
         const normalizedValue = value.toLowerCase().trim();
         const matches = map.filter(file => {
             const nameWithoutExt = file.name.split('.').slice(0, -1).join('.').toLowerCase();
             const fullName = file.name.toLowerCase();
             return fullName === normalizedValue || nameWithoutExt === normalizedValue;
         });
         
         if (matches.length === 1) {
             newRows[index].google_drive_file = matches[0];
             newRows[index].google_drive_error = null;
         } else if (matches.length > 1) {
             newRows[index].google_drive_file = null;
             newRows[index].google_drive_error = 'duplicate';
         } else {
             newRows[index].google_drive_file = null;
             newRows[index].google_drive_error = 'not_found';
         }
      }
    }
    
    if (field === 'title' || field === 'content') {
      const isValid = newRows[index].title && newRows[index].content && (!options.schedule_mode === 'excel' || (newRows[index].publish_date && newRows[index].publish_time));
      newRows[index].is_valid = isValid;
      newRows[index].status = isValid ? 'valid' : 'invalid';
    }
    setRows(newRows);
  };

  return (
    <div className="main-content">
      <div className="header-left" style={{ marginBottom: '20px' }}>
        <h2>Nhập lịch nội dung hàng loạt</h2>
      </div>

      {/* Progress */}
      <div style={{ display: 'flex', gap: '20px', marginBottom: '30px', borderBottom: '1px solid var(--border)', paddingBottom: '15px' }}>
        <div style={{ padding: '8px 16px', borderRadius: '8px', background: step === 1 ? 'var(--primary)' : 'transparent', color: step === 1 ? '#fff' : 'var(--text-muted)', fontWeight: step === 1 ? 'bold' : 'normal' }}>
          1. Chuẩn bị Dữ liệu
        </div>
        <div style={{ padding: '8px 16px', borderRadius: '8px', background: step === 2 ? 'var(--primary)' : 'transparent', color: step === 2 ? '#fff' : 'var(--text-muted)', fontWeight: step === 2 ? 'bold' : 'normal' }}>
          2. Xem trước & Chỉnh sửa
        </div>
        <div style={{ padding: '8px 16px', borderRadius: '8px', background: step === 3 ? 'var(--primary)' : 'transparent', color: step === 3 ? '#fff' : 'var(--text-muted)', fontWeight: step === 3 ? 'bold' : 'normal' }}>
          3. Hoàn tất
        </div>
      </div>

      {/* STEP 1 */}
      {step === 1 && (
        <div className="form-section">
          <div className="content-layout" style={{ gridTemplateColumns: '1fr 1fr', gap: '30px' }}>
            <div className="left-panel">
              <div className="form-group">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <label>File Excel hoặc CSV</label>
                  <a href={import.meta.env.VITE_API_BASE_URL?.replace('/api','') + '/Sample_Import_Template.xlsx'} download className="text-[#7c5cff] hover:underline text-sm font-medium">
                    ⬇ Tải file Excel mẫu
                  </a>
                </div>
                <div style={{ border: '2px dashed var(--border)', padding: '40px', textAlign: 'center', borderRadius: '8px', background: 'rgba(0,0,0,0.2)', marginTop: '8px' }}>
                  <input type="file" onChange={handleFileChange} accept=".xlsx,.csv" style={{ display: 'none' }} id="file-upload" />
                  <label htmlFor="file-upload" style={{ cursor: 'pointer', display: 'block' }}>
                    <div style={{ fontSize: '40px', marginBottom: '10px' }}>📁</div>
                    <div>{file ? `${file.name} (${(file.size / 1024).toFixed(1)} KB)` : 'Tải file lên'}</div>
                  </label>
                </div>
              </div>

              <div className="form-group" style={{ marginTop: '20px' }}>
                <label>Thương hiệu</label>
                <select value={options.brand_id} onChange={handleBrandChange} style={{ width: '100%', padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #555' }}>
                  <option value="">-- Chọn thương hiệu --</option>
                  {brands.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                </select>
              </div>

              <div className="form-group">
                <label>Facebook Page</label>
                <select value={options.facebook_page_id} onChange={e => setOptions({...options, facebook_page_id: e.target.value})} style={{ width: '100%', padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #555' }}>
                  <option value="">-- Chọn Facebook Page --</option>
                  {pages.map(p => <option key={p.id} value={p.id}>{p.page_name || p.name}</option>)}
                </select>
              </div>

              <div className="form-group" style={{ marginTop: '20px' }}>
                <label>Nguồn ảnh Google Drive (Tùy chọn)</label>
                <div style={{ display: 'flex', gap: '10px' }}>
                  <input 
                    type="text" 
                    placeholder="https://drive.google.com/drive/folders/..." 
                    value={options.google_drive_folder_url}
                    onChange={(e) => setOptions({...options, google_drive_folder_url: e.target.value})}
                    style={{ flex: 1, padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #555' }}
                  />
                  <button onClick={handleVerifyDrive} disabled={driveStatus === 'loading' || !options.google_drive_folder_url} className="btn btn-secondary">
                    {driveStatus === 'loading' ? 'Đang kiểm tra...' : 'Kiểm tra'}
                  </button>
                </div>
                {driveStatus === 'success' && driveStats && (
                  <div style={{ marginTop: '10px', color: '#4ade80', fontSize: '13px' }}>
                    ✓ Kết nối thành công — Thư mục: {driveStats.folder_name} ({driveStats.image_count} ảnh)
                  </div>
                )}
                {driveStatus === 'error' && (
                  <div style={{ marginTop: '10px', color: '#f87171', fontSize: '13px' }}>
                    ✗ Không có quyền truy cập hoặc link không hợp lệ
                  </div>
                )}
                
                <div style={{ marginTop: '10px' }}>
                  <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize: '14px' }}>
                    <input type="checkbox" checked={rememberDrive} onChange={(e) => setRememberDrive(e.target.checked)} />
                    Ghi nhớ thư mục này cho thương hiệu đã chọn
                  </label>
                </div>
              </div>
            </div>

            <div className="right-panel">
              <h3 style={{ borderBottom: '1px solid var(--border)', paddingBottom: '10px', marginBottom: '20px' }}>Phương án lên lịch</h3>
              
              <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer', padding: '15px', background: options.schedule_mode === 'excel' ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)', border: `1px solid ${options.schedule_mode === 'excel' ? 'var(--primary)' : 'var(--border)'}`, borderRadius: '8px' }}>
                  <input type="radio" name="schedule_mode" value="excel" checked={options.schedule_mode === 'excel'} onChange={() => setOptions({...options, schedule_mode: 'excel'})} style={{ width: '18px', height: '18px' }} />
                  <span style={{ fontWeight: 'bold' }}>Phương án 1: Dùng lịch có sẵn trong Excel</span>
                </label>
                
                <label style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer', padding: '15px', background: options.schedule_mode === 'tool' ? 'rgba(99, 102, 241, 0.1)' : 'rgba(0,0,0,0.2)', border: `1px solid ${options.schedule_mode === 'tool' ? 'var(--primary)' : 'var(--border)'}`, borderRadius: '8px' }}>
                  <input type="radio" name="schedule_mode" value="tool" checked={options.schedule_mode === 'tool'} onChange={() => setOptions({...options, schedule_mode: 'tool'})} style={{ width: '18px', height: '18px' }} />
                  <span style={{ fontWeight: 'bold' }}>Phương án 2: Dùng lịch cố định trên tool</span>
                </label>
              </div>

              {options.schedule_mode === 'tool' && (
                <div style={{ marginTop: '20px', padding: '20px', background: 'rgba(0,0,0,0.2)', borderRadius: '8px', border: '1px solid var(--border)' }}>
                  <div className="form-group">
                    <label>Ngày bắt đầu</label>
                    <input type="date" value={options.start_date} onChange={e => setOptions({...options, start_date: e.target.value})} style={{ width: '100%', padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #555' }} />
                  </div>

                  <div className="form-group">
                    <label>Số bài mỗi ngày</label>
                    <select value={options.frequency} onChange={handleFrequencyChange} style={{ width: '100%', padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #555' }}>
                      <option value="1">1 bài / ngày</option>
                      <option value="2">2 bài / ngày</option>
                      <option value="3">3 bài / ngày</option>
                    </select>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(120px, 1fr))', gap: '15px' }}>
                    {options.times.map((t, idx) => (
                      <div className="form-group" key={idx}>
                        <label>Khung giờ {idx + 1}</label>
                        <input type="time" value={t} onChange={e => handleTimeChange(idx, e.target.value)} style={{ width: '100%', padding: '10px', borderRadius: '4px', backgroundColor: '#333', color: '#fff', border: '1px solid #555' }} />
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
          
          <div style={{ marginTop: '30px', display: 'flex', justifyContent: 'flex-end' }}>
            <button className="btn-primary" onClick={handlePreview} disabled={loading} style={{ padding: '12px 24px', fontSize: '16px' }}>
              {loading ? 'Đang tải...' : 'Xem trước nội dung ➜'}
            </button>
          </div>
        </div>
      )}

      {/* STEP 2 */}
      {step === 2 && (
        <div className="result-display">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
             <h3>Kết quả phân tích</h3>
             <div style={{ display: 'flex', gap: '15px' }}>
                <span style={{ color: '#4caf50' }}>Hợp lệ: {previewData?.summary?.valid || 0}</span>
                <span style={{ color: '#f44336' }}>Không hợp lệ: {previewData?.summary?.invalid || 0}</span>
             </div>
          </div>

          <div style={{ overflowX: 'auto', background: 'rgba(0,0,0,0.2)', borderRadius: '8px', border: '1px solid var(--border)' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: '800px' }}>
              <thead>
                <tr style={{ background: 'rgba(255,255,255,0.05)', borderBottom: '1px solid var(--border)' }}>
                  <th style={{ padding: '12px', textAlign: 'center', width: '50px' }}>Bỏ</th>
                  <th style={{ padding: '12px', textAlign: 'left', width: '60px' }}>STT</th>
                  <th style={{ padding: '12px', textAlign: 'left' }}>Tiêu đề</th>
                  <th style={{ padding: '12px', textAlign: 'center', width: '120px' }}>Mã ảnh</th>
                  <th style={{ padding: '12px', textAlign: 'center', width: '120px' }}>Ảnh Drive</th>
                  <th style={{ padding: '12px', textAlign: 'left', width: '200px' }}>Ngày giờ đăng</th>
                  <th style={{ padding: '12px', textAlign: 'left', width: '200px' }}>Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, idx) => (
                  <tr key={idx} style={{ borderBottom: '1px solid rgba(255,255,255,0.05)', opacity: row.exclude ? 0.4 : 1 }}>
                    <td style={{ padding: '12px', textAlign: 'center' }}>
                      <input type="checkbox" checked={row.exclude || false} onChange={() => toggleExclude(idx)} />
                    </td>
                    <td style={{ padding: '12px' }}>{row._row_number || idx + 1}</td>
                    <td style={{ padding: '12px' }}>
                      <input 
                        style={{ width: '100%', padding: '8px', background: 'transparent', border: '1px solid var(--border)', borderRadius: '4px', color: '#fff' }} 
                        value={row.title} 
                        onChange={e => handleRowChange(idx, 'title', e.target.value)}
                      />
                    </td>
                    <td style={{ padding: '12px', textAlign: 'center' }}>
                      {previewData?.drive_images_map?.length > 0 ? (
                        <select 
                          style={{ width: '100%', padding: '6px', background: '#222', border: '1px solid var(--border)', borderRadius: '4px', color: '#fff', fontSize: '13px' }} 
                          value={row.image_key || ''} 
                          onChange={e => handleRowChange(idx, 'image_key', e.target.value)}
                        >
                          <option value="">-- Chọn ảnh --</option>
                          {previewData.drive_images_map.map(img => (
                            <option key={img.id} value={img.name}>{img.name}</option>
                          ))}
                        </select>
                      ) : (
                        <input 
                          style={{ width: '100%', padding: '6px', background: 'transparent', border: '1px solid var(--border)', borderRadius: '4px', color: '#fff', textAlign: 'center' }} 
                          value={row.image_key || ''} 
                          onChange={e => handleRowChange(idx, 'image_key', e.target.value)}
                          placeholder="Mã..."
                        />
                      )}
                    </td>
                    <td style={{ padding: '12px', textAlign: 'center' }}>
                      {row.google_drive_file ? (
                        <div style={{ display: 'flex', gap: '5px', justifyContent: 'center' }}>
                          <img src={row.google_drive_file.thumbnailLink || `https://drive.google.com/uc?export=view&id=${row.google_drive_file.id}`} style={{ width: '40px', height: '40px', objectFit: 'cover', borderRadius: '4px' }} alt="drive-img" />
                        </div>
                      ) : row.images?.length > 0 ? (
                        <div style={{ display: 'flex', gap: '5px', justifyContent: 'center' }}>
                          <img src={import.meta.env.VITE_API_BASE_URL?.replace('/api','') + '/storage/' + row.images[0]} style={{ width: '40px', height: '40px', objectFit: 'cover', borderRadius: '4px' }} alt="img" onError={(e) => { e.target.style.display = 'none'; e.target.parentElement.innerHTML += '🖼️' }} />
                        </div>
                      ) : (
                        <span style={{ color: 'var(--text-muted)' }}>Không có</span>
                      )}
                    </td>
                    <td style={{ padding: '12px' }}>
                      {options.schedule_mode === 'excel' ? (
                        <div style={{ display: 'flex', gap: '5px' }}>
                          <input type="date" style={{ width: '55%', padding: '6px', background: '#222', border: '1px solid var(--border)', borderRadius: '4px', color: '#fff' }} value={row.publish_date || ''} onChange={e => handleRowChange(idx, 'publish_date', e.target.value)} />
                          <input type="time" style={{ width: '45%', padding: '6px', background: '#222', border: '1px solid var(--border)', borderRadius: '4px', color: '#fff' }} value={row.publish_time || ''} onChange={e => handleRowChange(idx, 'publish_time', e.target.value)} />
                        </div>
                      ) : (
                        <span style={{ color: '#fff' }}>{row.publish_date} {row.publish_time}</span>
                      )}
                    </td>
                    <td style={{ padding: '12px' }}>
                      <span style={{ color: row.is_valid ? '#4caf50' : '#f44336', fontWeight: 'bold' }}>
                        {row.is_valid ? 'Hợp lệ' : 'Lỗi'}
                      </span>
                      {row.errors?.length > 0 && (
                        <div style={{ color: '#f44336', fontSize: '12px', marginTop: '4px' }}>
                          {row.errors[0]}
                        </div>
                      )}
                      {row.google_drive_error && (
                        <div style={{ color: '#f59e0b', fontSize: '12px', marginTop: '4px' }}>
                          {row.google_drive_error === 'not_found' ? 'Không tìm thấy ảnh trên Drive' : 'Trùng tên ảnh trên Drive'}
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '30px' }}>
            <button className="btn-secondary" onClick={() => setStep(1)}>⬅ Quay lại</button>
            <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
              <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                <input type="checkbox" checked={saveAsDraft} onChange={e => setSaveAsDraft(e.target.checked)} />
                Chỉ nhập bài, chưa lên lịch
              </label>
              <button className="btn-primary" onClick={handleConfirm} disabled={loading} style={{ background: '#4caf50', borderColor: '#4caf50', padding: '12px 24px' }}>
                {loading ? 'Đang xử lý...' : 'Import và lên lịch 🚀'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* STEP 3 */}
      {step === 3 && (
        <div className="form-section" style={{ textAlign: 'center', padding: '60px 20px', borderColor: '#4caf50' }}>
          <div style={{ fontSize: '80px', marginBottom: '20px' }}>🎉</div>
          <h2 style={{ color: '#4caf50', marginBottom: '10px' }}>Import thành công!</h2>
          <p style={{ color: 'var(--text-muted)', marginBottom: '40px' }}>Quá trình import bài viết đã hoàn tất.</p>
          <div style={{ display: 'flex', justifyContent: 'center', gap: '20px' }}>
            <button className="btn-secondary" onClick={() => navigate('/posts')}>Xem danh sách bài viết</button>
            <button className="btn-primary" onClick={() => { setStep(1); setFile(null); setRows([]); setPreviewData(null); setSaveAsDraft(false); }}>Import File khác</button>
          </div>
        </div>
      )}
    </div>
  );
};

export default PostImport;
