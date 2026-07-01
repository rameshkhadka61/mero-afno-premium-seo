jQuery(document).ready(function($) {
    const $title = $('#eseo_meta_title');
    const $desc = $('#eseo_meta_description');
    const $keyword = $('#eseo_focus_keyword');
    const $results = $('#eseo-analysis-results');
    const $content = $('#content'); // Classic editor

    // Inject progress bars into DOM
    $title.after('<div class="eseo-progress-container"><div id="eseo-title-progress" class="eseo-progress-bar"></div></div>');
    $desc.after('<div class="eseo-progress-container"><div id="eseo-desc-progress" class="eseo-progress-bar"></div></div>');

    function updateProgressBar($el, currentLength, maxLength, warningThreshold) {
        let percentage = (currentLength / maxLength) * 100;
        if (percentage > 100) percentage = 100;
        
        $el.css('width', percentage + '%');
        $el.removeClass('warning danger');

        if (currentLength > maxLength) {
            $el.addClass('danger');
        } else if (currentLength > warningThreshold) {
            $el.addClass('warning');
        }
    }
    
    // SERP Preview Elements
    const $serpTitle = $('#eseo-serp-title-preview');
    const $serpDesc  = $('#eseo-serp-desc-preview');
    const $serpUrl   = $('#eseo-serp-url-preview');

    function updateSerpPreview(titleVal, descVal) {
        // Title: use SEO title, fall back to WP post title
        let displayTitle = titleVal;
        if (!displayTitle) {
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                displayTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            }
            if (!displayTitle) displayTitle = $('#title').val() || '';
        }
        $serpTitle.text(displayTitle || 'Your Post Title Here');

        // Description: use meta desc, show placeholder if empty
        $serpDesc.text(descVal || 'Please provide a meta description. If you don\'t, Google will try to find a relevant part of your post to show in the search results.');

        // URL: use current post permalink if available
        let slug = '';
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            slug = wp.data.select('core/editor').getEditedPostAttribute('slug') || '';
        }
        if (!slug) {
            const titleForSlug = displayTitle.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            slug = titleForSlug;
        }
        $serpUrl.text(eseo_vars.site_url + '/' + slug + '/');
        return displayTitle;
    }

    function updateSocialPreview(displayTitle, descVal, thumbUrl) {
        const socTitle = $('#eseo_social_title').val() || displayTitle || 'Your Post Title';
        const socDesc = $('#eseo_social_description').val() || descVal || 'Your post description preview will appear here.';
        let socImg = $('#eseo_social_image').val() || thumbUrl;
        if (socImg && typeof socImg === 'string') {
            socImg = socImg.replace(/-\d+x\d+(?=\.[a-zA-Z]{3,4}($|\?))/i, '');
        }

        $('#eseo-fb-title-preview, #eseo-tw-title-preview').text(socTitle);
        $('#eseo-fb-desc-preview, #eseo-tw-desc-preview').text(socDesc);

        if (socImg) {
            $('#eseo-fb-img-preview, #eseo-tw-img-preview').css({
                'background-image': 'url(' + socImg + ')',
                'background-size': 'contain',
                'background-position': 'center center',
                'background-repeat': 'no-repeat'
            }).text('');
        } else {
            $('#eseo-fb-img-preview, #eseo-tw-img-preview').css('background-image', 'none').text('No Image Selected');
        }
    }

    function analyzeSEO() {
        let resultsHTML = '';
        const keywordVal = $keyword.val() ? $keyword.val().trim().toLowerCase() : '';
        const titleVal = $title.val() ? $title.val().trim() : '';
        const descVal = $desc.val() ? $desc.val().trim() : '';
        let contentVal = '';
        
        // Update progress bars
        updateProgressBar($('#eseo-title-progress'), titleVal.length, 60, 50);
        updateProgressBar($('#eseo-desc-progress'), descVal.length, 160, 140);

        // Update SERP preview
        let displayTitle = updateSerpPreview(titleVal, descVal);
        
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            // Gutenberg
            const editor = wp.data.select('core/editor');
            contentVal = editor ? editor.getEditedPostContent() : '';
        } else {
            // Classic
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                contentVal = tinymce.get('content').getContent({format: 'text'});
            } else if ($content.length) {
                contentVal = $content.val();
            }
        }

        // Featured Image Check & SERP Thumbnail
        let hasFeaturedImage = false;
        let thumbUrl = '';
        const $serpThumb = $('#eseo-serp-thumb-preview');

        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            const featuredImageId = wp.data.select('core/editor').getEditedPostAttribute('featured_media');
            hasFeaturedImage = featuredImageId && featuredImageId > 0;
            if (hasFeaturedImage) {
                const media = wp.data.select('core').getMedia(featuredImageId);
                if (media && media.source_url) {
                    thumbUrl = media.source_url;
                } else if (media && media.media_details && media.media_details.sizes && media.media_details.sizes.full) {
                    thumbUrl = media.media_details.sizes.full.source_url;
                } else {
                    const $editorImg = $('.editor-post-featured-image__preview img, .components-responsive-wrapper__content');
                    if ($editorImg.length && $editorImg.attr('src')) {
                        thumbUrl = $editorImg.attr('src');
                    }
                }
            }
        } else {
            // Classic editor
            hasFeaturedImage = $('#_thumbnail_id').length && parseInt($('#_thumbnail_id').val()) > 0;
            if (hasFeaturedImage) {
                const $classicImg = $('#set-post-thumbnail img');
                if ($classicImg.length && $classicImg.attr('src')) {
                    thumbUrl = $classicImg.attr('src');
                }
            }
        }

        if (thumbUrl && typeof thumbUrl === 'string') {
            thumbUrl = thumbUrl.replace(/-\d+x\d+(?=\.[a-zA-Z]{3,4}($|\?))/i, '');
        }

        if (hasFeaturedImage && thumbUrl) {
            $serpThumb.css({
                'background-image': 'url(' + thumbUrl + ')',
                'background-size': 'contain',
                'background-position': 'center center',
                'background-repeat': 'no-repeat'
            }).show();
        } else if (hasFeaturedImage) {
            $serpThumb.css('background-image', 'none').show();
        } else {
            $serpThumb.hide();
        }

        updateSocialPreview(displayTitle, descVal, thumbUrl);

        if (hasFeaturedImage) {
            resultsHTML += '<li style="color:green;">✅ Post has a featured image set.</li>';
        } else {
            resultsHTML += '<li style="color:red;">❌ No featured image set. Google may not show a rich result.</li>';
        }

        if (!keywordVal) {
            resultsHTML += '<li style="color:red;">❌ Please enter a focus keyword.</li>';
        } else {
            // Check keyword in title
            if (titleVal.toLowerCase().includes(keywordVal) || (titleVal === '' && $('#title').val() && $('#title').val().toLowerCase().includes(keywordVal))) {
                resultsHTML += '<li style="color:green;">✅ Focus keyword found in SEO title.</li>';
            } else {
                resultsHTML += '<li style="color:red;">❌ Focus keyword not found in SEO title.</li>';
            }

            // Check keyword in description
            if (descVal.toLowerCase().includes(keywordVal)) {
                resultsHTML += '<li style="color:green;">✅ Focus keyword found in meta description.</li>';
            } else {
                resultsHTML += '<li style="color:red;">❌ Focus keyword not found in meta description.</li>';
            }

            // Check keyword in content
            if (contentVal && contentVal.toLowerCase().includes(keywordVal)) {
                resultsHTML += '<li style="color:green;">✅ Focus keyword found in content.</li>';
            } else {
                resultsHTML += '<li style="color:red;">❌ Focus keyword not found in content.</li>';
            }
        }

        // Title length check
        const actualTitleLength = titleVal ? titleVal.length : ($('#title').val() ? $('#title').val().length : 0);
        if (actualTitleLength > 0 && actualTitleLength <= 60) {
            resultsHTML += '<li style="color:green;">✅ SEO title length is good (' + actualTitleLength + ' chars).</li>';
        } else if (actualTitleLength > 60) {
            resultsHTML += '<li style="color:orange;">⚠️ SEO title is too long.</li>';
        }

        // Description length check
        if (descVal.length >= 120 && descVal.length <= 160) {
            resultsHTML += '<li style="color:green;">✅ Meta description length is good (' + descVal.length + ' chars).</li>';
        } else {
            resultsHTML += '<li style="color:orange;">⚠️ Meta description length should be between 120-160 chars.</li>';
        }

        $results.html(resultsHTML);
    }

    // Bind events
    $title.on('input', analyzeSEO);
    $desc.on('input', analyzeSEO);
    $keyword.on('input', analyzeSEO);
    $('#title').on('input', analyzeSEO); // Default WP title
    $('#eseo_social_title, #eseo_social_description, #eseo_social_image').on('input', analyzeSEO);

    // SERP Preview: Desktop / Mobile Toggle
    $('#eseo-serp-desktop-btn').on('click', function() {
        $('#eseo-serp-preview-box').removeClass('mobile-view');
        $('#eseo-serp-mobile-btn').removeClass('active');
        $(this).addClass('active');
    });
    $('#eseo-serp-mobile-btn').on('click', function() {
        $('#eseo-serp-preview-box').addClass('mobile-view');
        $('#eseo-serp-desktop-btn').removeClass('active');
        $(this).addClass('active');
    });

    // For Gutenberg, listen to content changes
    if (typeof wp !== 'undefined' && wp.data) {
        wp.data.subscribe(function () {
            // Throttling could be added here for performance
            analyzeSEO();
        });
    }

    // AI Generation
    $('.eseo-ai-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const type = $btn.data('type');
        const keyword = $keyword.val() ? $keyword.val().trim() : '';

        // If not generating keyword, require a keyword first
        if (type !== 'keyword' && !keyword) {
            alert('Please enter a Focus Keyword first.');
            return;
        }

        let postTitle = '';
        if (type === 'keyword') {
            postTitle = $('#title').val() ? $('#title').val().trim() : '';
            if (!postTitle && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                postTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            }
            if (!postTitle) {
                alert('Please enter a Post Title at the top of the editor first so the AI knows what your post is about.');
                return;
            }
        }

        const originalText = $btn.text();
        $btn.text('⏳ Generating...').prop('disabled', true);

        let contentVal = '';
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            const editor = wp.data.select('core/editor');
            contentVal = editor ? editor.getEditedPostContent() : '';
        } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            contentVal = tinymce.get('content').getContent({format: 'text'});
        } else if ($content.length) {
            contentVal = $content.val();
        }

        const postId = $('#post_ID').length ? $('#post_ID').val() : '';

        $.ajax({
            url: eseo_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'eseo_generate_meta',
                nonce: eseo_vars.ai_nonce,
                post_id: postId,
                type: type,
                keyword: keyword,
                post_title: postTitle,
                content: contentVal.substring(0, 3000) // limit content sent
            },
            success: function(response) {
                if (response.success) {
                    if (type === 'title') {
                        $title.val(response.data).trigger('input');
                    } else if (type === 'description') {
                        $desc.val(response.data).trigger('input');
                    } else if (type === 'keyword') {
                        $keyword.val(response.data).trigger('input');
                    }
                } else {
                    if (response.data && response.data.code === 'no_api_key') {
                        if (confirm(response.data.message + '\n\nClick OK to go to settings.')) {
                            window.location.href = 'admin.php?page=eseo-ai-settings';
                        }
                    } else {
                        alert('Error: ' + (response.data.message || response.data));
                    }
                }
            },
            error: function() {
                alert('AJAX request failed.');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });

    // Initial analysis
    setTimeout(analyzeSEO, 1000);

    // LSI Keywords Suggestion
    $('.eseo-lsi-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const keyword = $keyword.val() ? $keyword.val().trim() : $('#title').val() || '';
        if (!keyword) {
            alert('Please enter a focus keyword or post title first.');
            return;
        }
        $btn.text('⏳ Suggesting...').prop('disabled', true);
        const postId = $('#post_ID').val() || 0;
        $.ajax({
            url: eseo_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'eseo_suggest_lsi',
                nonce: eseo_vars.ai_nonce,
                post_id: postId,
                keyword: keyword
            },
            success: function(response) {
                if (response.success && response.data) {
                    let html = '<div style="font-size:12px; font-weight:bold; color:#1d2327; margin-bottom:4px;">Suggested LSI / Semantic Keywords (Click to copy):</div><div style="display:flex; flex-wrap:wrap; gap:5px;">';
                    response.data.forEach(function(term) {
                        html += '<span class="eseo-lsi-tag" style="background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:12px; font-size:12px; cursor:pointer;" title="Click to copy">' + term + '</span>';
                    });
                    html += '</div>';
                    $('#eseo-lsi-results').html(html).show();
                } else {
                    alert('Could not suggest LSI keywords: ' + (response.data && response.data.message ? response.data.message : 'No results'));
                }
            },
            error: function() { alert('AJAX failed'); },
            complete: function() { $btn.text('🤖 Suggest LSI Keywords').prop('disabled', false); }
        });
    });

    $(document).on('click', '.eseo-lsi-tag', function() {
        const text = $(this).text();
        navigator.clipboard.writeText(text);
        const originalBg = $(this).css('background');
        $(this).css('background', '#bbf7d0').text('✓ Copied!');
        setTimeout(() => { $(this).css('background', '#e0f2fe').text(text); }, 1200);
    });

    // Internal Link Opportunities Scan
    $('#eseo-find-links-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const keyword = $keyword.val() ? $keyword.val().trim() : $('#title').val() || '';
        const postId = $('#post_ID').val() || 0;
        $btn.text('⏳ Scanning site...').prop('disabled', true);
        $.ajax({
            url: eseo_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'eseo_get_link_suggestions',
                nonce: eseo_vars.ai_nonce,
                post_id: postId,
                keyword: keyword
            },
            success: function(response) {
                if (response.success && response.data && response.data.length) {
                    let html = '<div style="font-weight:bold; color:#1d2327;">Found ' + response.data.length + ' related articles to link to:</div>';
                    response.data.forEach(function(item) {
                        html += '<div style="border:1px solid #ccd0d4; padding:10px; border-radius:6px; background:#fff; display:flex; justify-content:space-between; align-items:center;">' +
                            '<div><a href="' + item.url + '" target="_blank" style="font-weight:600;">' + item.title + '</a><br><small style="color:#666;">' + item.url + '</small></div>' +
                            '<button type="button" class="button button-small eseo-copy-link-btn" data-anchor="&lt;a href=&quot;' + item.url + '&quot;&gt;' + (keyword || item.title) + '&lt;/a&gt;">📋 Copy HTML</button>' +
                        '</div>';
                    });
                    $('#eseo-links-results').html(html);
                } else {
                    $('#eseo-links-results').html('<p style="color:#666; font-style:italic;">No related articles found matching this topic.</p>');
                }
            },
            error: function() { alert('AJAX failed'); },
            complete: function() { $btn.text('🔍 Scan For Internal Link Opportunities').prop('disabled', false); }
        });
    });

    $(document).on('click', '.eseo-copy-link-btn', function() {
        const anchor = $(this).data('anchor');
        navigator.clipboard.writeText(anchor);
        $(this).text('✓ Copied HTML!');
        setTimeout(() => { $(this).text('📋 Copy HTML'); }, 1500);
    });

    // Dark Mode Toggle Check
    if (localStorage.getItem('eseo_dark_mode') === '1') {
        $('body').addClass('eseo-dark-mode');
    }

    $(document).on('click', '#eseo-dark-mode-toggle', function(e) {
        e.preventDefault();
        if ($('body').hasClass('eseo-dark-mode')) {
            $('body').removeClass('eseo-dark-mode');
            localStorage.setItem('eseo_dark_mode', '0');
        } else {
            $('body').addClass('eseo-dark-mode');
            localStorage.setItem('eseo_dark_mode', '1');
        }
    });

    analyzeSEO();
    setTimeout(analyzeSEO, 600);
    setTimeout(analyzeSEO, 2000);
});
