/* global DTPOST, tinyMCE */
(function () {
	'use strict';

	/* ── Helpers ───────────────────────────────────────────────────────────── */
	function qs(s, c)  { return (c || document).querySelector(s); }
	function qsa(s, c) { return Array.from((c || document).querySelectorAll(s)); }
	function esc(s)    { var d = document.createElement('div'); d.textContent = String(s||''); return d.innerHTML; }

	function showNotice(msg, type) {
		var box = qs('#dtpost-notice');
		var txt = qs('#dtpost-notice-msg');
		if (!box) { alert(msg); return; }
		box.className = 'dtpost-notice dtpost-notice-' + (type || 'error');
		if (txt) txt.textContent = msg;
		box.style.display = 'flex';
		box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
	function hideNotice() {
		var box = qs('#dtpost-notice');
		if (box) box.style.display = 'none';
	}

	function slugify(s) {
		return (s||'').toLowerCase()
			.replace(/[^\w\s-]/g,'').replace(/[\s_]+/g,'-').replace(/^-+|-+$/g,'');
	}

	/* =========================================================================
	   UPLOAD PAGE
	   ========================================================================= */
	var uploadForm = qs('#dtpost-upload-form');
	if (uploadForm) {
		var docxDrop  = qs('#dtpost-docx-dropzone');
		var docxInput = qs('#docx_file');
		var imgInput  = qs('#featured_image');
		var imgThumb  = qs('#dtpost-img-thumb-inner');
		var progWrap  = qs('#dtpost-progress');
		var progFill  = qs('#dtpost-prog-fill');
		var progLabel = qs('#dtpost-prog-label');
		var uploadBtn = qs('#dtpost-upload-btn');
		var errBox    = qs('#dtpost-upload-error');
		var docxName  = qs('#dtpost-docx-name');

		function showUploadErr(msg) {
			if (!errBox) return;
			errBox.textContent = msg;
			errBox.style.display = 'block';
		}

		/* Drag-drop */
		if (docxDrop) {
			['dragenter','dragover'].forEach(function(e) {
				docxDrop.addEventListener(e, function(ev) { ev.preventDefault(); docxDrop.classList.add('dtpost-drag-over'); });
			});
			['dragleave','drop'].forEach(function(e) {
				docxDrop.addEventListener(e, function(ev) { ev.preventDefault(); docxDrop.classList.remove('dtpost-drag-over'); });
			});
			docxDrop.addEventListener('drop', function(ev) {
				var f = ev.dataTransfer && ev.dataTransfer.files[0];
				if (f) setDocx(f);
			});
		}
		if (docxInput) docxInput.addEventListener('change', function() { if (this.files[0]) setDocx(this.files[0]); });

		function setDocx(file) {
			if (errBox) errBox.style.display = 'none';
			if (file.name.split('.').pop().toLowerCase() !== 'docx') { showUploadErr(DTPOST.strings.invalid_type); return; }
			if (DTPOST.max_mb && file.size > DTPOST.max_mb * 1024 * 1024) { showUploadErr(DTPOST.strings.file_too_large); return; }
			try { var dt = new DataTransfer(); dt.items.add(file); docxInput.files = dt.files; } catch(e) {}
			if (docxDrop)  docxDrop.classList.add('dtpost-has-file');
			if (docxName)  docxName.textContent = file.name;
		}

		if (imgInput) {
			imgInput.addEventListener('change', function() {
				var f = this.files[0];
				if (!f || !imgThumb) return;
				var r = new FileReader();
				r.onload = function(e) {
					var oldImg = qs('img', imgThumb);
					if (oldImg) oldImg.remove();
					var svg = qs('svg', imgThumb);
					if (svg) svg.style.display = 'none';
					var img = document.createElement('img');
					img.src = e.target.result;
					img.style.width = '100%';
					img.style.height = '100%';
					img.style.objectFit = 'cover';
					imgThumb.appendChild(img);
				};
				r.readAsDataURL(f);
			});
		}

		uploadForm.addEventListener('submit', function(e) {
			e.preventDefault();
			if (errBox) errBox.style.display = 'none';
			if (!docxInput || !docxInput.files || !docxInput.files[0]) { showUploadErr(DTPOST.strings.invalid_type); return; }

			var fd = new FormData(uploadForm);
			fd.set('action', 'dtpost_upload_docx');
			fd.set('nonce',  DTPOST.nonce);

			if (uploadBtn) uploadBtn.disabled = true;
			if (progWrap) progWrap.style.display = 'block';
			if (progFill)  progFill.style.width = '10%';
			if (progLabel) progLabel.textContent = DTPOST.strings.uploading;

			var xhr = new XMLHttpRequest();
			xhr.open('POST', DTPOST.ajax_url);
			xhr.upload.onprogress = function(ev) {
				if (ev.lengthComputable && progFill)
					progFill.style.width = Math.round(ev.loaded/ev.total*70) + '%';
			};
			xhr.onload = function() {
				if (progFill)  progFill.style.width = '90%';
				if (progLabel) progLabel.textContent = DTPOST.strings.parsing;
				var resp;
				try { resp = JSON.parse(xhr.responseText); } catch(ex) { resp = null; }
				if (resp && resp.success && resp.data && resp.data.redirect) {
					if (progFill) {
						progFill.style.width = '100%';
						progFill.style.background = '#00a32a';
					}
					if (progLabel) progLabel.textContent = 'Complete! Redirecting...';
					setTimeout(function() {
						window.location.href = resp.data.redirect;
					}, 400);
				} else {
					showUploadErr((resp && resp.data && resp.data.message) || DTPOST.strings.error);
					if (uploadBtn) uploadBtn.disabled = false;
					if (progWrap) progWrap.style.display = 'none';
					if (progFill) progFill.style.width = '0';
				}
			};
			xhr.onerror = function() {
				showUploadErr(DTPOST.strings.error);
				if (uploadBtn) uploadBtn.disabled = false;
				if (progWrap)  progWrap.style.display = 'none';
			};
			xhr.send(fd);
		});
	}

	/* =========================================================================
	   PREVIEW + PUBLISH PAGE
	   ========================================================================= */
	var publishBtn   = qs('#dtpost-publish-btn');
	var saveDraftBtn = qs('#dtpost-save-draft');

	if (publishBtn || saveDraftBtn) {

		/* Elements */
		var titleInput    = qs('#dtpost-post-title');
		var slugDisplay   = qs('#dtpost-slug-display');
		var slugInput     = qs('#dtpost-post-slug');
		var slugEditBtn   = qs('#dtpost-slug-edit-btn');
		var slugEditWrap  = qs('#dtpost-slug-edit-wrap');
		var slugOkBtn     = qs('#dtpost-slug-ok');
		var slugCancelBtn = qs('#dtpost-slug-cancel');
		var statusSel     = qs('#dtpost-post-status');
		var postTypeSel   = qs('#dtpost-post-type');
		var authorSel     = qs('#dtpost-post-author');
		var pubLabel      = qs('#dtpost-pub-label');
		var modal         = qs('#dtpost-modal');
		var modalOk       = qs('#dtpost-modal-ok');
		var modalCancel   = qs('#dtpost-modal-cancel');
		var modalX        = qs('#dtpost-modal-x');
		var modalTitle    = qs('#dtpost-modal-title');
		var modalBody     = qs('#dtpost-modal-body');

		/* ── Slug editing ──────────────────────────────────────────────────── */
		if (slugEditBtn && slugEditWrap) {
			slugEditBtn.addEventListener('click', function(e) {
				e.preventDefault();
				slugEditWrap.style.display = 'flex';
				slugEditBtn.style.display  = 'none';
				if (slugInput) slugInput.focus();
			});
		}
		function applySlug() {
			if (!slugInput) return;
			var s = slugify(slugInput.value);
			slugInput.value = s;
			if (slugDisplay) slugDisplay.textContent = s;
			if (slugEditWrap) slugEditWrap.style.display = 'none';
			if (slugEditBtn)  slugEditBtn.style.display  = 'inline-flex';
		}
		if (slugOkBtn)     slugOkBtn.addEventListener('click', function(e) { e.preventDefault(); applySlug(); });
		if (slugCancelBtn) slugCancelBtn.addEventListener('click', function(e) { e.preventDefault(); applySlug(); });
		if (slugInput) {
			slugInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter')  { e.preventDefault(); applySlug(); }
				if (e.key === 'Escape') { e.preventDefault(); applySlug(); }
			});
		}

		/* ── Title → slug sync ─────────────────────────────────────────────── */
		if (titleInput) {
			titleInput.addEventListener('input', function() {
				var s = slugify(this.value);
				if (slugInput)   slugInput.value         = s;
				if (slugDisplay) slugDisplay.textContent = s;
			});
		}

		/* ── Status → button label ─────────────────────────────────────────── */
		function syncStatus() {
			if (!statusSel) return;
			var v = statusSel.value;
			var labels = { publish: 'Publish', draft: 'Save Draft', pending: 'Submit for Review', private: 'Publish Privately' };
			if (pubLabel) pubLabel.textContent = labels[v] || 'Publish';
		}
		if (statusSel) { statusSel.addEventListener('change', syncStatus); syncStatus(); }

		/* ── Tags ──────────────────────────────────────────────────────────── */
		initTags();

		/* ── Modal wiring ──────────────────────────────────────────────────── */
		function closeModal() { if (modal) modal.style.display = 'none'; }
		if (modalCancel) modalCancel.addEventListener('click', closeModal);
		if (modalX)      modalX.addEventListener('click', closeModal);
		if (modal)       modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
		document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

		if (modalOk) {
			modalOk.addEventListener('click', function() { closeModal(); doPublish(); });
		}

		/* ── Save Draft shortcut ───────────────────────────────────────────── */
		if (saveDraftBtn) {
			saveDraftBtn.addEventListener('click', function() {
				if (statusSel) statusSel.value = 'draft';
				syncStatus();
				triggerPublish(true); // pass true to skip modal
			});
		}

		/* ── Publish button ────────────────────────────────────────────────── */
		if (publishBtn) publishBtn.addEventListener('click', function() { triggerPublish(false); });

		function triggerPublish(skipModal) {
			hideNotice();
			var title = titleInput ? titleInput.value.trim() : '';
			if (!title) {
				showNotice('Please enter a post title before publishing.'); return;
			}

			if (skipModal === true) {
				doPublish();
				return;
			}

			/* Build modal summary */
			var status = statusSel ? statusSel.value : 'publish';
			var postType = postTypeSel ? postTypeSel.options[postTypeSel.selectedIndex].text : 'Post';
			var statusLabels = { publish: 'Publish now', draft: 'Save as draft', pending: 'Submit for review', private: 'Publish privately' };
			var statusLabel = statusLabels[status] || status;

			var html = '<p><strong>Title:</strong> '   + esc(title)       + '</p>' +
			           '<p><strong>Type:</strong> '    + esc(postType)    + '</p>' +
			           '<p><strong>Status:</strong> '  + esc(statusLabel) + '</p>';

			var cats = qsa('input[name="dtpost_category[]"]:checked').map(function(c) {
				var lbl = c.closest('label');
				return lbl ? (lbl.querySelector('.dtpost-cat-name') ? lbl.querySelector('.dtpost-cat-name').textContent.trim() : lbl.textContent.trim()) : '';
			}).filter(Boolean).join(', ') || '—';
			html += '<p><strong>Categories:</strong> ' + esc(cats) + '</p>';

			if (modalBody)  modalBody.innerHTML  = html;
			if (modalTitle) modalTitle.textContent = status === 'draft' ? 'Save as draft?' : 'Ready to publish?';
			if (modalOk)    modalOk.textContent    = status === 'draft' ? 'Save Draft'     : 'Confirm & Publish';

			if (modal) modal.style.display = 'flex';
		}

		/* ── AJAX publish ──────────────────────────────────────────────────── */
		function doPublish() {
			if (publishBtn) {
				publishBtn.disabled = true;
				var existingSvg = publishBtn.querySelector('svg');
				if (existingSvg) {
					existingSvg.outerHTML = '<svg class="dtpost-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-dasharray="32" stroke-dashoffset="14" opacity="0.3"></circle><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor"></path></svg>';
				}
			}
			if (saveDraftBtn) { saveDraftBtn.disabled = true; }
			if (pubLabel)     pubLabel.textContent = DTPOST.strings.publishing || 'Publishing…';

			/* Get TinyMCE content */
			var content = '';
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('dtpost_content_editor')) {
				content = tinyMCE.get('dtpost_content_editor').getContent();
			} else {
				var ta = qs('#dtpost_content_editor');
				content = ta ? ta.value : '';
			}

			var fd = new FormData();
			fd.append('action',       'dtpost_publish_post');
			fd.append('nonce',        DTPOST.nonce);
			fd.append('post_title',   titleInput ? titleInput.value.trim() : '');
			fd.append('post_content', content);
			fd.append('post_excerpt', qs('#dtpost-excerpt') ? qs('#dtpost-excerpt').value.trim() : '');
			fd.append('post_status',  statusSel ? statusSel.value : 'publish');
			fd.append('post_slug',    slugInput  ? slugInput.value.trim()  : '');
			fd.append('post_author',  authorSel  ? authorSel.value         : '');
			fd.append('post_type',    postTypeSel ? postTypeSel.value : 'post');

			qsa('input[name="dtpost_category[]"]:checked').forEach(function(c) { fd.append('post_category[]', c.value); });

			var tagsHidden = qs('#dtpost-tags-hidden');
			if (tagsHidden && tagsHidden.value) fd.append('post_tags', tagsHidden.value);

			fetch(DTPOST.ajax_url, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(resp) {
					if (resp && resp.success && resp.data && resp.data.redirect) {
						window.location.href = resp.data.redirect;
					} else {
						showNotice((resp && resp.data && resp.data.message) || DTPOST.strings.error);
						resetBtns();
					}
				})
				.catch(function(err) {
					showNotice(DTPOST.strings.error + (err.message ? ' — ' + err.message : ''));
					resetBtns();
				});
		}

		var originalPubHtml = publishBtn ? publishBtn.innerHTML : '';

		function resetBtns() {
			if (publishBtn) {
				publishBtn.disabled = false;
				publishBtn.innerHTML = originalPubHtml;
			}
			if (saveDraftBtn) saveDraftBtn.disabled = false;
			syncStatus();
		}
	}

	/* =========================================================================
	   TAG INPUT
	   ========================================================================= */
	function initTags() {
		var wrap    = qs('#dtpost-tag-wrap');
		var pillBox = qs('#dtpost-tag-pills');
		var input   = qs('#dtpost-tag-input');
		var suggest = qs('#dtpost-tag-suggest');
		var hidden  = qs('#dtpost-tags-hidden');
		if (!input) return;

		var tags = [], results = [], activeIdx = -1, debTimer;

		if (wrap) wrap.addEventListener('click', function(e) { if (e.target === wrap || e.target === pillBox) input.focus(); });

		function renderPills() {
			if (!pillBox) return;
			pillBox.innerHTML = '';
			tags.forEach(function(tag, i) {
				var pill = document.createElement('span');
				pill.className = 'dtpost-tag-pill';
				pill.innerHTML = esc(tag) + '<button type="button" class="dtpost-pill-x" data-i="' + i + '">&#215;</button>';
				pillBox.appendChild(pill);
			});
			if (hidden) hidden.value = tags.join(',');
		}

		if (pillBox) pillBox.addEventListener('click', function(e) {
			if (e.target.dataset.i !== undefined) { tags.splice(Number(e.target.dataset.i), 1); renderPills(); }
		});

		function addTag(name) {
			name = name.trim();
			if (!name || tags.indexOf(name) !== -1) return;
			tags.push(name); renderPills(); input.value = '';
			if (suggest) suggest.style.display = 'none';
			activeIdx = -1;
		}

		input.addEventListener('keydown', function(e) {
			if (e.key === 'Enter' || e.key === ',') {
				e.preventDefault();
				addTag(activeIdx >= 0 && results[activeIdx] ? results[activeIdx].name : this.value);
			} else if (e.key === 'ArrowDown') {
				activeIdx = Math.min(activeIdx + 1, results.length - 1); hilite();
			} else if (e.key === 'ArrowUp') {
				activeIdx = Math.max(activeIdx - 1, -1); hilite();
			} else if (e.key === 'Backspace' && !this.value && tags.length) {
				tags.pop(); renderPills();
			}
		});

		function hilite() {
			if (!suggest) return;
			qsa('li', suggest).forEach(function(li, i) { li.style.background = i === activeIdx ? '#f0f6fc' : ''; });
		}

		input.addEventListener('input', function() {
			clearTimeout(debTimer);
			var q = this.value.trim();
			if (q.length < 1) { if (suggest) suggest.style.display = 'none'; return; }
			debTimer = setTimeout(function() {
				fetch(DTPOST.ajax_url + '?action=dtpost_tag_search&nonce=' + DTPOST.nonce + '&q=' + encodeURIComponent(q))
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (!resp || !resp.success || !suggest) return;
						results = resp.data || [];
						suggest.innerHTML = '';
						if (!results.length) { suggest.style.display = 'none'; return; }
						results.forEach(function(t) {
							var li = document.createElement('li');
							li.textContent = t.name;
							li.addEventListener('mousedown', function(ev) { ev.preventDefault(); addTag(t.name); });
							suggest.appendChild(li);
						});
						suggest.style.display = 'block';
					});
			}, 200);
		});
		document.addEventListener('click', function(e) {
			if (suggest && e.target !== input && !suggest.contains(e.target)) suggest.style.display = 'none';
		});
	}

	/* =========================================================================
	   SETTINGS PAGE — Clear Temp Files
	   ========================================================================= */
	var clearTempBtn = qs('#dtpost-clear-temp');
	if (clearTempBtn) {
		clearTempBtn.addEventListener('click', function() {
			if (!confirm('Clear all temporary files?')) return;
			clearTempBtn.disabled = true;
			var fd = new FormData();
			fd.append('action', 'dtpost_clear_temp');
			fd.append('nonce', DTPOST.nonce);
			fetch(DTPOST.ajax_url, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(r) {
					clearTempBtn.disabled = false;
					alert(r.success ? 'Temp files cleared.' : 'Error clearing files.');
				})
				.catch(function() { clearTempBtn.disabled = false; });
		});
	}

}());

/* ── Contextual notice dismissal ──────────────────────────────────────────
   WordPress draws the × on an .is-dismissible notice and hides it, but does
   not persist anything. This records the dismissal so the notice does not
   come back on the next page load.                                        */
(function () {
	document.addEventListener('click', function (e) {
		// Two shapes: WordPress draws .notice-dismiss on its own notices, and
		// the in-page deadline panel draws its own close button.
		var btn = e.target.closest('.notice-dismiss, .dtpost-deadline__dismiss');
		if (!btn) { return; }

		var notice = btn.closest('[data-dtpost-notice]');
		if (!notice || typeof DTPOST === 'undefined') { return; }

		// WordPress hides its own notices. This one is ours, so hide it here.
		if (btn.classList.contains('dtpost-deadline__dismiss')) { notice.style.display = 'none'; }

		var body = new FormData();
		body.append('action', 'dtpost_dismiss_notice');
		body.append('nonce', DTPOST.nonce);
		body.append('key', notice.getAttribute('data-dtpost-notice'));

		fetch(DTPOST.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' })
			.catch(function () { /* It is hidden for this page either way. */ });
	});
})();

/* ── Undo an import ───────────────────────────────────────────────────────
   Trashes the post that was just created, so a first import feels
   reversible. Trash, not delete — it is still recoverable afterwards.   */
(function () {
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-dtpost-trash]');
		if (!btn || typeof DTPOST === 'undefined') { return; }

		if (!window.confirm(DTPOST.strings.confirm_trash)) { return; }

		btn.disabled = true;

		var body = new FormData();
		body.append('action', 'dtpost_trash_post');
		body.append('nonce', DTPOST.nonce);
		body.append('post_id', btn.getAttribute('data-dtpost-trash'));

		fetch(DTPOST.ajax_url, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success && res.data && res.data.redirect) {
					window.location.href = res.data.redirect;
					return;
				}
				btn.disabled = false;
				window.alert((res && res.data && res.data.message) || DTPOST.strings.error);
			})
			.catch(function () {
				btn.disabled = false;
				window.alert(DTPOST.strings.error);
			});
	});
})();
