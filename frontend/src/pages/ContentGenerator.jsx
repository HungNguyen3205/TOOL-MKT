import React, { useState, useRef } from 'react';
import { generateContent } from '../api';
import { createPost, updatePost } from '../api/posts';
import ContentForm from '../components/ContentForm';
import ResultDisplay from '../components/ResultDisplay';

const ContentGenerator = () => {
  const [generating, setGenerating] = useState(false);
  const [results, setResults] = useState(null);
  const [metadata, setMetadata] = useState(null);
  const [errorMsg, setErrorMsg] = useState(null);
  
  const [savedPostId, setSavedPostId] = useState(null);
  const [isSaving, setIsSaving] = useState(false);
  
  const lastPayload = useRef(null);

  const handleGenerate = async (payload) => {
    setGenerating(true);
    setErrorMsg(null);
    setSavedPostId(null);
    lastPayload.current = payload;

    try {
      const response = await generateContent(payload);
      if (response.success && response.data) {
        setResults(response.data.versions);
        setMetadata(response.data.metadata);
      }
    } catch (err) {
      if (err.message) {
        let msg = err.message;
        if (err.errors) {
            const validationErrors = Object.values(err.errors).flat().join(' ');
            msg += ' ' + validationErrors;
        }
        setErrorMsg(msg);
      } else {
        setErrorMsg('Không thể kết nối với server. Vui lòng kiểm tra lại.');
      }
    } finally {
      setGenerating(false);
    }
  };

  const handleRegenerate = () => {
    if (lastPayload.current) {
      handleGenerate(lastPayload.current);
    }
  };

  const handleSaveVersion = async (version, versionNumber) => {
    setIsSaving(true);
    try {
      const payload = {
        title: version.title,
        content: version.content,
        cta: version.cta,
        hashtags: version.hashtags,
        objective: lastPayload.current.objective || 'sales',
        tone: lastPayload.current.tone || 'friendly',
        content_length: lastPayload.current.length || 'medium',
        source: 'ai_generated',
        status: 'draft',
        selected_version: versionNumber,
        source_input: lastPayload.current
      };

      if (savedPostId) {
        await updatePost(savedPostId, payload);
        alert('Đã cập nhật bản nháp thành công.');
      } else {
        const res = await createPost(payload);
        setSavedPostId(res.data.id);
        alert('Đã lưu bản nháp thành công.');
      }
    } catch (err) {
      alert('Lỗi khi lưu bản nháp: ' + (err.message || 'Chưa rõ nguyên nhân'));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <>
      <h2>Tạo nội dung Facebook</h2>
      
      {errorMsg && (
        <div className="error-alert">
          {errorMsg}
        </div>
      )}

      {generating && (
        <div className="info-alert">
          AI đang xử lý... quá trình này có thể mất một vài giây.
        </div>
      )}

      <div className="content-layout">
        <div className="form-section">
            <ContentForm onSubmit={handleGenerate} loading={generating} />
        </div>
        
        <div className="result-section">
            {results ? (
              <ResultDisplay 
                results={results} 
                metadata={metadata}
                onRegenerate={handleRegenerate} 
                loading={generating} 
                onSave={handleSaveVersion}
                savedPostId={savedPostId}
                isSaving={isSaving}
              />
            ) : (
              <div className="empty-state">
                Chưa có kết quả sinh nội dung. Vui lòng điền thông tin và bấm "Tạo nội dung".
              </div>
            )}
        </div>
      </div>
    </>
  );
};

export default ContentGenerator;
