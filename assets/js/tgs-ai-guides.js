(function () {
    'use strict';

    var config = window.TGSAIGuidesConfig || null;
    if (!config || !config.tour) {
        return;
    }

    var state = {
        panelOpen: false,
        activeDriver: null,
        markedSeen: false,
        typingNode: null
    };

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function text(value) {
        return value == null ? '' : String(value);
    }

    function label(key, fallback) {
        var labels = config.labels || {};
        return text(labels[key] || fallback || '');
    }

    function requestErrorMessage(error) {
        return error && error.message ? error.message : label('requestFailed', 'Không gọi được khung hỗ trợ lúc này. Vui lòng thử lại sau.');
    }

    function getDriverFactory() {
        if (window.driver && window.driver.js && typeof window.driver.js.driver === 'function') {
            return window.driver.js.driver;
        }
        if (typeof window.driver === 'function') {
            return window.driver;
        }
        return null;
    }

    function post(action, data) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', config.nonce);

        Object.keys(data || {}).forEach(function (key) {
            body.set(key, data[key]);
        });

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (response) {
            return response.text().then(function (payload) {
                var json = null;
                if (payload) {
                    try {
                        json = JSON.parse(payload);
                    } catch (error) {
                        json = null;
                    }
                }

                if (!response.ok || !json) {
                    var message = json && json.data && json.data.message ? json.data.message : '';
                    if (!message && !json && response.redirected) {
                        message = label('sessionExpired', 'Phiên đăng nhập có thể đã hết hạn. Vui lòng tải lại trang hoặc đăng nhập lại.');
                    }
                    if (!message && !response.ok) {
                        message = response.statusText;
                    }
                    throw new Error(message || label('requestFailed', 'Không gọi được khung hỗ trợ lúc này. Vui lòng thử lại sau.'));
                }

                return json;
            });
        });
    }

    function markSeen(source) {
        if (state.markedSeen) {
            return Promise.resolve();
        }

        state.markedSeen = true;

        return post('tgs_ai_guides_mark_seen', {
            page: config.page || 'tgs-shop-management',
            view: config.view,
            version: config.tour.version,
            source: source || 'tour'
        }).catch(function () {});
    }

    function createButton(className, label, iconClass) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        if (iconClass) {
            var icon = document.createElement('i');
            icon.className = iconClass;
            button.appendChild(icon);
        }
        var span = document.createElement('span');
        span.textContent = label;
        button.appendChild(span);
        return button;
    }

    function createShell() {
        if (document.getElementById('tgsAiGuideLauncher')) {
            return;
        }

        var launcher = createButton('tgs-ai-guide-launcher', config.labels.launcher, 'bx bx-bot');
        launcher.id = 'tgsAiGuideLauncher';
        launcher.setAttribute('aria-haspopup', 'dialog');
        launcher.setAttribute('aria-controls', 'tgsAiGuidePanel');

        var panel = document.createElement('section');
        panel.id = 'tgsAiGuidePanel';
        panel.className = 'tgs-ai-guide-panel';
        panel.setAttribute('aria-hidden', 'true');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', config.labels.panelTitle);

        panel.innerHTML = [
            '<div class="tgs-ai-guide-panel__header">',
                '<div>',
                    '<div class="tgs-ai-guide-panel__title"></div>',
                    '<div class="tgs-ai-guide-panel__subtitle"></div>',
                '</div>',
                '<button type="button" class="tgs-ai-guide-panel__close" aria-label="Đóng"><i class="bx bx-x"></i></button>',
            '</div>',
            '<div class="tgs-ai-guide-panel__body">',
                '<div class="tgs-ai-guide-actions">',
                    '<button type="button" class="tgs-ai-guide-action" data-tgs-ai-replay><i class="bx bx-play"></i><span></span></button>',
                    '<button type="button" class="tgs-ai-guide-action" data-tgs-ai-skip><i class="bx bx-hide"></i><span></span></button>',
                '</div>',
                '<div class="tgs-ai-guide-messages" role="log" aria-live="polite"></div>',
                '<div class="tgs-ai-guide-quick"></div>',
            '</div>',
            '<form class="tgs-ai-guide-form">',
                '<input type="text" class="tgs-ai-guide-input" autocomplete="off">',
                '<button type="submit" class="tgs-ai-guide-send"><i class="bx bx-send"></i><span></span></button>',
            '</form>'
        ].join('');

        document.body.appendChild(launcher);
        document.body.appendChild(panel);

        panel.querySelector('.tgs-ai-guide-panel__title').textContent = config.labels.panelTitle;
        panel.querySelector('.tgs-ai-guide-panel__subtitle').textContent = config.tour.title + ' - ' + config.labels.panelSubtitle;
        panel.querySelector('[data-tgs-ai-replay] span').textContent = config.labels.replayTour;
        panel.querySelector('[data-tgs-ai-skip] span').textContent = config.labels.skipPage;
        panel.querySelector('.tgs-ai-guide-input').setAttribute('placeholder', config.labels.askPlaceholder);
        panel.querySelector('.tgs-ai-guide-send span').textContent = config.labels.send;

        bindShellEvents(launcher, panel);
        appendMessage('assistant', config.tour.summary);
        renderQuickQuestions(config.tour.quickQuestions || []);
    }

    function bindShellEvents(launcher, panel) {
        launcher.addEventListener('click', function () {
            togglePanel(true);
        });

        panel.querySelector('.tgs-ai-guide-panel__close').addEventListener('click', function () {
            togglePanel(false);
        });

        panel.querySelector('[data-tgs-ai-replay]').addEventListener('click', function () {
            togglePanel(false);
            startTour('manual');
        });

        panel.querySelector('[data-tgs-ai-skip]').addEventListener('click', function () {
            if (state.activeDriver) {
                state.activeDriver.destroy();
            }
            markSeen('skip-button').then(function () {
                appendMessage('assistant', label('skipConfirmed', 'Đã ghi nhận bỏ qua hướng dẫn cho trang này. Bạn vẫn có thể bấm "Hướng dẫn lại" bất cứ lúc nào.'));
            });
        });

        panel.querySelector('.tgs-ai-guide-form').addEventListener('submit', function (event) {
            event.preventDefault();
            var input = panel.querySelector('.tgs-ai-guide-input');
            var question = input.value.trim();
            if (!question) {
                return;
            }
            input.value = '';
            ask(question);
        });

        document.addEventListener('click', function (event) {
            if (!state.panelOpen) {
                return;
            }
            if (panel.contains(event.target) || launcher.contains(event.target)) {
                return;
            }
            togglePanel(false);
        });

        document.querySelectorAll('[data-tgs-ai-replay]').forEach(function (button) {
            if (button.closest('#tgsAiGuidePanel')) {
                return;
            }
            button.addEventListener('click', function () {
                startTour('manual');
            });
        });

        document.querySelectorAll('[data-tgs-ai-reset-site]').forEach(function (button) {
            button.addEventListener('click', function () {
                post('tgs_ai_guides_reset_seen', {
                    scope: 'site',
                    page: config.page || 'tgs-shop-management',
                    view: config.view,
                    version: config.tour.version
                }).then(function () {
                    appendMessage('assistant', label('resetConfirmed', 'Đã reset lịch sử hướng dẫn đã xem cho tài khoản hiện tại trên website này.'));
                    togglePanel(true);
                }).catch(function (error) {
                    appendMessage('assistant', requestErrorMessage(error));
                    togglePanel(true);
                });
            });
        });
    }

    function togglePanel(open) {
        var panel = document.getElementById('tgsAiGuidePanel');
        var launcher = document.getElementById('tgsAiGuideLauncher');
        if (!panel || !launcher) {
            return;
        }

        state.panelOpen = !!open;
        panel.classList.toggle('is-open', state.panelOpen);
        panel.setAttribute('aria-hidden', state.panelOpen ? 'false' : 'true');
        launcher.setAttribute('aria-expanded', state.panelOpen ? 'true' : 'false');

        if (state.panelOpen) {
            var input = panel.querySelector('.tgs-ai-guide-input');
            setTimeout(function () {
                input.focus();
            }, 80);
        }
    }

    function appendMessage(role, message) {
        var box = document.querySelector('.tgs-ai-guide-messages');
        if (!box) {
            return;
        }

        var item = document.createElement('div');
        item.className = 'tgs-ai-guide-message tgs-ai-guide-message--' + role;
        item.textContent = message;
        box.appendChild(item);
        box.scrollTop = box.scrollHeight;
    }

    function showTyping(show) {
        var box = document.querySelector('.tgs-ai-guide-messages');
        if (!box) {
            return;
        }

        if (!show && state.typingNode) {
            state.typingNode.remove();
            state.typingNode = null;
            return;
        }

        if (show && !state.typingNode) {
            state.typingNode = document.createElement('div');
            state.typingNode.className = 'tgs-ai-guide-message tgs-ai-guide-message--assistant is-typing';
            state.typingNode.textContent = config.labels.typing;
            box.appendChild(state.typingNode);
            box.scrollTop = box.scrollHeight;
        }
    }

    function renderQuickQuestions(questions) {
        var holder = document.querySelector('.tgs-ai-guide-quick');
        if (!holder) {
            return;
        }

        holder.innerHTML = '';
        questions.slice(0, 5).forEach(function (question) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'tgs-ai-guide-chip';
            chip.textContent = question;
            chip.addEventListener('click', function () {
                ask(question);
            });
            holder.appendChild(chip);
        });
    }

    function ask(question) {
        appendMessage('user', question);
        showTyping(true);
        togglePanel(true);

        post('tgs_ai_guides_chat', {
            page: config.page || 'tgs-shop-management',
            view: config.view,
            question: question
        }).then(function (response) {
            showTyping(false);
            if (!response || !response.success || !response.data) {
                appendMessage('assistant', response && response.data && response.data.message ? text(response.data.message) : label('emptyAnswer', 'Mình chưa trả lời được câu này trong bộ hướng dẫn hiện tại.'));
                return;
            }
            appendMessage('assistant', text(response.data.answer));
            if (response.data.quickQuestions) {
                renderQuickQuestions(response.data.quickQuestions);
            }
        }).catch(function (error) {
            showTyping(false);
            appendMessage('assistant', requestErrorMessage(error));
        });
    }

    function isVisibleElement(element) {
        if (!element || !element.getClientRects) {
            return false;
        }

        var rects = element.getClientRects();
        if (!rects || !rects.length) {
            return false;
        }

        var styles = window.getComputedStyle ? window.getComputedStyle(element) : null;
        if (styles && (styles.display === 'none' || styles.visibility === 'hidden' || styles.opacity === '0')) {
            return false;
        }

        return true;
    }

    function queryStepElement(selector) {
        if (!selector) {
            return null;
        }

        var selectors = selector.split(',');
        for (var i = 0; i < selectors.length; i++) {
            var item = selectors[i].trim();
            if (!item) {
                continue;
            }
            var found = null;
            try {
                found = document.querySelector(item);
            } catch (error) {
                found = null;
            }
            if (found && isVisibleElement(found)) {
                return found;
            }
        }

        return null;
    }

    function buildDriverSteps() {
        var rawSteps = config.tour.steps || [];
        var steps = [];

        rawSteps.forEach(function (step) {
            var element = queryStepElement(step.element);
            if (step.element && !element) {
                return;
            }

            steps.push({
                element: element || undefined,
                popover: {
                    title: text(step.title),
                    description: text(step.description),
                    side: step.side || 'bottom',
                    align: step.align || 'center'
                }
            });
        });

        if (!steps.length) {
            steps.push({
                popover: {
                    title: config.tour.title,
                    description: config.labels.tourUnavailable,
                    side: 'over',
                    align: 'center'
                }
            });
        }

        return steps;
    }

    function startTour(source) {
        var driverFactory = getDriverFactory();
        if (!driverFactory) {
            appendMessage('assistant', label('driverMissing', 'Không tìm thấy driver.js trên trang. Kiểm tra lại asset của plugin TGS AI Guides.'));
            togglePanel(true);
            return;
        }

        if (state.activeDriver && state.activeDriver.isActive && state.activeDriver.isActive()) {
            state.activeDriver.destroy();
        }

        state.markedSeen = false;
        var steps = buildDriverSteps();

        state.activeDriver = driverFactory({
            steps: steps,
            animate: true,
            smoothScroll: true,
            allowClose: true,
            overlayClickBehavior: 'close',
            stagePadding: 8,
            stageRadius: 8,
            popoverClass: 'tgs-ai-driver-popover',
            showProgress: true,
            progressText: '{{current}}/{{total}}',
            nextBtnText: label('nextStep', 'Tiếp'),
            prevBtnText: label('prevStep', 'Quay lại'),
            doneBtnText: label('doneStep', 'Hoàn tất'),
            onPopoverRender: function (popover, opts) {
                if (popover.closeButton) {
                    popover.closeButton.setAttribute('aria-label', label('closeTour', 'Bỏ qua hướng dẫn'));
                }
                if (popover.footerButtons && !popover.footerButtons.querySelector('.tgs-driver-skip')) {
                    var skip = document.createElement('button');
                    skip.type = 'button';
                    skip.className = 'tgs-driver-skip';
                    skip.textContent = label('skipTour', 'Bỏ qua');
                    skip.addEventListener('click', function () {
                        markSeen('skip-in-tour');
                        opts.driver.destroy();
                    });
                    popover.footerButtons.insertBefore(skip, popover.footerButtons.firstChild);
                }
            },
            onCloseClick: function (element, step, opts) {
                markSeen('close-tour');
                opts.driver.destroy();
            },
            onDestroyed: function () {
                markSeen(source || 'tour-destroyed');
                state.activeDriver = null;
            }
        });

        state.activeDriver.drive();
    }

    ready(function () {
        createShell();

        window.TGSAIGuides = {
            open: function () { togglePanel(true); },
            close: function () { togglePanel(false); },
            startTour: startTour,
            ask: ask
        };

        if (config.autoStart) {
            setTimeout(function () {
                startTour('auto-first-load');
            }, 900);
        }
    });
})();
