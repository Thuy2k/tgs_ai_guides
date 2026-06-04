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
        typingNode: null,
        chatScope: 'page',
        lastTourHadGlobalSteps: false,
        guidesEnabled: true,
        quickExpanded: false,
        lastQuickQuestions: []
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

    function globalStepsStorageKey() {
        return [
            'tgs_ai_guides_global_steps_seen',
            config.siteId || 0,
            config.userId || 0,
            config.tour.version || 'v1'
        ].join('_');
    }

    function guidesDisabledStorageKey() {
        return [
            'tgs_ai_guides_disabled',
            config.siteId || 0,
            config.userId || 0
        ].join('_');
    }

    function areGuidesDisabled() {
        try {
            return window.localStorage.getItem(guidesDisabledStorageKey()) === '1';
        } catch (error) {
            return false;
        }
    }

    function setGuidesDisabled(disabled) {
        try {
            if (disabled) {
                window.localStorage.setItem(guidesDisabledStorageKey(), '1');
            } else {
                window.localStorage.removeItem(guidesDisabledStorageKey());
            }
        } catch (error) {}

        state.guidesEnabled = !disabled;
        syncGuidesEnabledUi();
    }

    function getGlobalStepCooldownMs() {
        var minutes = Number(config.globalStepCooldownMinutes);
        if (!isFinite(minutes)) {
            minutes = 180;
        }
        return Math.max(0, minutes) * 60 * 1000;
    }

    function isGlobalStep(step) {
        return step && step.scope === 'global';
    }

    function areGlobalStepsDue() {
        var cooldown = getGlobalStepCooldownMs();
        if (cooldown <= 0) {
            return true;
        }

        try {
            var lastSeen = Number(window.localStorage.getItem(globalStepsStorageKey()) || 0);
            return !lastSeen || (Date.now() - lastSeen) >= cooldown;
        } catch (error) {
            return true;
        }
    }

    function markGlobalStepsSeen() {
        try {
            window.localStorage.setItem(globalStepsStorageKey(), String(Date.now()));
        } catch (error) {}
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

    function projectQuickQuestions() {
        return (config.tour && config.tour.projectQuickQuestions) || config.projectQuickQuestions || [];
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
                '<div class="tgs-ai-guide-panel__tools">',
                    '<button type="button" class="tgs-ai-guide-panel__toggle" data-tgs-ai-toggle-all aria-label=""></button>',
                    '<button type="button" class="tgs-ai-guide-panel__close" aria-label="Đóng"><i class="bx bx-x"></i></button>',
                '</div>',
            '</div>',
            '<div class="tgs-ai-guide-panel__body">',
                '<div class="tgs-ai-guide-actions">',
                    '<button type="button" class="tgs-ai-guide-action" data-tgs-ai-replay><i class="bx bx-play"></i><span></span></button>',
                    '<button type="button" class="tgs-ai-guide-action" data-tgs-ai-skip><i class="bx bx-hide"></i><span></span></button>',
                '</div>',
                '<div class="tgs-ai-guide-scope" role="group" aria-label="Phạm vi hỏi đáp">',
                    '<button type="button" class="is-active" data-tgs-ai-scope="page"></button>',
                    '<button type="button" data-tgs-ai-scope="project"></button>',
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
        panel.querySelector('[data-tgs-ai-scope="page"]').textContent = label('scopePage', 'Trang này');
        panel.querySelector('[data-tgs-ai-scope="project"]').textContent = label('scopeProject', 'Toàn dự án');
        panel.querySelector('.tgs-ai-guide-input').setAttribute('placeholder', config.labels.askPlaceholder);
        panel.querySelector('.tgs-ai-guide-send span').textContent = config.labels.send;

        bindShellEvents(launcher, panel);
        appendMessage('assistant', config.tour.summary);
        renderQuickQuestions(config.tour.quickQuestions || []);
        syncGuidesEnabledUi();
    }

    function syncGuidesEnabledUi() {
        var launcher = document.getElementById('tgsAiGuideLauncher');
        var panel = document.getElementById('tgsAiGuidePanel');
        var enabled = state.guidesEnabled;

        document.body.classList.toggle('tgs-ai-guides-disabled', !enabled);

        if (launcher) {
            launcher.classList.toggle('is-disabled', !enabled);
            launcher.setAttribute('aria-label', enabled ? label('launcher', 'AI hỗ trợ') : label('enableAll', 'Bật gợi ý toàn bộ'));
            launcher.setAttribute('title', enabled ? label('launcher', 'AI hỗ trợ') : label('enableAll', 'Bật gợi ý toàn bộ'));

            var launcherIcon = launcher.querySelector('i');
            var launcherText = launcher.querySelector('span');
            if (launcherIcon) {
                launcherIcon.className = enabled ? 'bx bx-bot' : 'bx bx-show';
            }
            if (launcherText) {
                launcherText.textContent = enabled ? label('launcher', 'AI hỗ trợ') : label('enableAll', 'Bật gợi ý');
            }
        }

        if (!panel) {
            return;
        }

        panel.classList.toggle('is-guides-disabled', !enabled);
        panel.querySelectorAll('[data-tgs-ai-replay], [data-tgs-ai-skip]').forEach(function (button) {
            button.disabled = !enabled;
        });

        var toggle = panel.querySelector('[data-tgs-ai-toggle-all]');
        if (toggle) {
            toggle.innerHTML = '<i class="bx ' + (enabled ? 'bx-hide' : 'bx-show') + '"></i>';
            toggle.setAttribute('aria-label', enabled ? label('disableAll', 'Tắt gợi ý toàn bộ') : label('enableAll', 'Bật gợi ý toàn bộ'));
            toggle.setAttribute('title', enabled ? label('disableAll', 'Tắt gợi ý toàn bộ') : label('enableAll', 'Bật gợi ý toàn bộ'));
            toggle.classList.toggle('is-disabled', !enabled);
        }
    }

    function bindShellEvents(launcher, panel) {
        launcher.addEventListener('click', function () {
            if (!state.guidesEnabled) {
                setGuidesDisabled(false);
                appendMessage('assistant', label('enabledNotice', 'Đã bật lại AI hỗ trợ hướng dẫn cho toàn bộ trang.'));
            }
            togglePanel(true);
        });

        panel.querySelector('.tgs-ai-guide-panel__close').addEventListener('click', function () {
            togglePanel(false);
        });

        panel.querySelector('[data-tgs-ai-replay]').addEventListener('click', function () {
            togglePanel(false);
            startTour('manual');
        });

        panel.querySelector('[data-tgs-ai-toggle-all]').addEventListener('click', function () {
            var shouldDisable = state.guidesEnabled;
            if (shouldDisable && state.activeDriver) {
                state.activeDriver.destroy();
            }
            setGuidesDisabled(shouldDisable);
            if (shouldDisable) {
                appendMessage('assistant', label('disabledNotice', 'Đã tắt gợi ý tự động trên toàn bộ trang cho tài khoản này. Bấm nút nhỏ "Bật gợi ý" khi cần dùng lại.'));
                togglePanel(false);
            } else {
                appendMessage('assistant', label('enabledNotice', 'Đã bật lại AI hỗ trợ hướng dẫn cho toàn bộ trang.'));
                togglePanel(true);
            }
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

        panel.querySelectorAll('[data-tgs-ai-scope]').forEach(function (button) {
            button.addEventListener('click', function () {
                state.chatScope = button.getAttribute('data-tgs-ai-scope') === 'project' ? 'project' : 'page';
                state.quickExpanded = false;
                panel.querySelectorAll('[data-tgs-ai-scope]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
                renderQuickQuestions(state.chatScope === 'project' ? projectQuickQuestions() : (config.tour.quickQuestions || []));
            });
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

        state.lastQuickQuestions = Array.isArray(questions) ? questions.slice() : [];
        holder.innerHTML = '';
        var limit = state.chatScope === 'project' ? 8 : 5;
        var visibleQuestions = state.quickExpanded ? state.lastQuickQuestions : state.lastQuickQuestions.slice(0, limit);

        visibleQuestions.forEach(function (question) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'tgs-ai-guide-chip';
            chip.textContent = question;
            chip.addEventListener('click', function () {
                ask(question);
            });
            holder.appendChild(chip);
        });

        if (state.lastQuickQuestions.length > limit) {
            var more = document.createElement('button');
            more.type = 'button';
            more.className = 'tgs-ai-guide-chip tgs-ai-guide-chip--more';
            more.textContent = state.quickExpanded
                ? label('showFewerQuestions', 'Thu gọn')
                : label('showMoreQuestions', 'Xem thêm') + ' +' + (state.lastQuickQuestions.length - limit);
            more.addEventListener('click', function () {
                state.quickExpanded = !state.quickExpanded;
                renderQuickQuestions(state.lastQuickQuestions);
            });
            holder.appendChild(more);
        }
    }

    function ask(question) {
        appendMessage('user', question);
        showTyping(true);
        togglePanel(true);

        post('tgs_ai_guides_chat', {
            page: config.page || 'tgs-shop-management',
            view: config.view,
            question: question,
            scope: state.chatScope
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
        var globalStepsDue = areGlobalStepsDue();
        var includedGlobalSteps = false;

        rawSteps.forEach(function (step) {
            if (isGlobalStep(step) && !globalStepsDue) {
                return;
            }

            var element = queryStepElement(step.element);
            if (step.element && !element) {
                return;
            }

            if (isGlobalStep(step)) {
                includedGlobalSteps = true;
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

        state.lastTourHadGlobalSteps = includedGlobalSteps;

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
        if (!state.guidesEnabled) {
            appendMessage('assistant', label('disabledNotice', 'Đã tắt gợi ý tự động trên toàn bộ trang cho tài khoản này. Bấm nút nhỏ "Bật gợi ý" khi cần dùng lại.'));
            togglePanel(true);
            return;
        }

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
                if (state.lastTourHadGlobalSteps) {
                    markGlobalStepsSeen();
                    state.lastTourHadGlobalSteps = false;
                }
                markSeen(source || 'tour-destroyed');
                state.activeDriver = null;
            }
        });

        state.activeDriver.drive();
    }

    ready(function () {
        state.guidesEnabled = !areGuidesDisabled();
        createShell();

        window.TGSAIGuides = {
            open: function () { togglePanel(true); },
            close: function () { togglePanel(false); },
            startTour: startTour,
            ask: ask
        };

        if (state.guidesEnabled && config.autoStart) {
            setTimeout(function () {
                startTour('auto-first-load');
            }, 900);
        }
    });
})();
