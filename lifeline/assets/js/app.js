/**
 * LifeLine Blood Network — Premium UI JavaScript v2.0
 * Animations | Particles | Toasts | Charts | Interactions
 */

(function() {
    'use strict';

    // =========================================================
    // PARTICLE CANVAS BACKGROUND
    // =========================================================
    function initParticles() {
        const canvas = document.getElementById('particle-canvas');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        let particles = [];
        let animationId;
        let isActive = true;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        const isMobile = window.innerWidth < 768;
        const PARTICLE_COUNT = isMobile ? 30 : Math.min(80, Math.floor(window.innerWidth / 20));
        const CONNECTION_DISTANCE = isMobile ? 100 : 150;

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
                this.radius = Math.random() * 2 + 1;
                this.opacity = Math.random() * 0.5 + 0.2;
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;

                if (this.x < 0 || this.x > canvas.width) this.vx *= -1;
                if (this.y < 0 || this.y > canvas.height) this.vy *= -1;
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(230, 57, 70, ${this.opacity})`;
                ctx.fill();
            }
        }

        function init() {
            particles = [];
            for (let i = 0; i < PARTICLE_COUNT; i++) {
                particles.push(new Particle());
            }
        }

        function drawConnections() {
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);

                    if (dist < CONNECTION_DISTANCE) {
                        const opacity = (1 - dist / CONNECTION_DISTANCE) * 0.15;
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = `rgba(230, 57, 70, ${opacity})`;
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                }
            }
        }

        function animate() {
            if (!isActive) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            drawConnections();
            
            animationId = requestAnimationFrame(animate);
        }

        // Pause when tab hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                isActive = false;
                cancelAnimationFrame(animationId);
            } else {
                isActive = true;
                animate();
            }
        });

        init();
        animate();
    }

    // =========================================================
    // HEADER SCROLL EFFECT
    // =========================================================
    function initHeaderScroll() {
        const header = document.querySelector('header');
        if (!header) return;
        
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            lastScroll = currentScroll;
        }, { passive: true });
    }

    // =========================================================
    // MOBILE MENU
    // =========================================================
    function initMobileMenu() {
        const toggle = document.getElementById('mobileMenuToggle');
        const nav = document.querySelector('.nav-links');
        if (!toggle || !nav) return;

        toggle.addEventListener('click', () => {
            const isActive = nav.classList.toggle('active');
            toggle.classList.toggle('active', isActive);
            toggle.setAttribute('aria-expanded', isActive.toString());
        });

        // Close on link click
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                nav.classList.remove('active');
                toggle.classList.remove('active');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!nav.contains(e.target) && !toggle.contains(e.target) && nav.classList.contains('active')) {
                nav.classList.remove('active');
                toggle.classList.remove('active');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // =========================================================
    // TOAST NOTIFICATIONS
    // =========================================================
    window.LifeLine = window.LifeLine || {};
    
    window.LifeLine.toast = function(message, type = 'info', duration = 4000) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            success: '&#10003;',
            error: '&#10007;',
            warning: '&#9888;',
            info: '&#8505;'
        };

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div>${message}</div>
        `;

        container.appendChild(toast);

        // Auto remove
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 400);
        }, duration);
    };

    // =========================================================
    // ANIMATED COUNTERS
    // =========================================================
    window.LifeLine.animateCounter = function(element, target, duration = 2000) {
        const start = 0;
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            const current = Math.floor(start + (target - start) * eased);
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = target.toLocaleString();
            }
        }
        
        requestAnimationFrame(update);
    };

    // Initialize counters on scroll
    function initCounters() {
        const counters = document.querySelectorAll('[data-counter]');
        if (!counters.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.dataset.counter);
                    window.LifeLine.animateCounter(entry.target, target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    }

    // =========================================================
    // INTERSECTION OBSERVER FOR ANIMATIONS
    // =========================================================
    function initScrollAnimations() {
        const animatedElements = document.querySelectorAll('[data-animate]');
        if (!animatedElements.length) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const animation = entry.target.dataset.animate;
                    entry.target.classList.add(animation);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        animatedElements.forEach(el => observer.observe(el));
    }

    // =========================================================
    // TABS
    // =========================================================
    function initTabs() {
        document.querySelectorAll('.tabs').forEach(tabGroup => {
            const buttons = tabGroup.querySelectorAll('.tab-btn');
            const panels = tabGroup.parentElement?.querySelectorAll('.tab-panel');
            if (!panels) return;

            buttons.forEach((btn, index) => {
                btn.addEventListener('click', () => {
                    buttons.forEach(b => b.classList.remove('active'));
                    panels.forEach(p => p.classList.remove('active'));
                    btn.classList.add('active');
                    if (panels[index]) panels[index].classList.add('active');
                });
            });
        });
    }

    // =========================================================
    // MODAL
    // =========================================================
    window.LifeLine.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    };

    window.LifeLine.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
    };

    function initModals() {
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });

        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.modal-overlay')?.classList.remove('active');
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => {
                    m.classList.remove('active');
                });
            }
        });
    }

    // =========================================================
    // NOTIFICATION DROPDOWN
    // =========================================================
    function initNotificationDropdown() {
        const bell = document.querySelector('.notification-bell');
        const dropdown = document.querySelector('.notification-dropdown');
        if (!bell || !dropdown) return;

        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    }

    // =========================================================
    // SMOOTH SCROLL
    // =========================================================
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    // =========================================================
    // CHARTS (Canvas-based simple charts)
    // =========================================================
    window.LifeLine.drawBarChart = function(canvasId, data, labels, color) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);

        const padding = 40;
        const chartWidth = rect.width - padding * 2;
        const chartHeight = rect.height - padding * 2;
        const maxValue = Math.max(...data) * 1.1;
        const barWidth = (chartWidth / data.length) * 0.6;
        const barGap = (chartWidth / data.length) * 0.4;

        // Clear
        ctx.clearRect(0, 0, rect.width, rect.height);

        // Draw bars
        data.forEach((value, i) => {
            const x = padding + i * (barWidth + barGap) + barGap / 2;
            const barHeight = (value / maxValue) * chartHeight;
            const y = padding + chartHeight - barHeight;

            // Gradient
            const gradient = ctx.createLinearGradient(0, y, 0, y + barHeight);
            gradient.addColorStop(0, color || '#e63946');
            gradient.addColorStop(1, 'rgba(230, 57, 70, 0.3)');

            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.roundRect(x, y, barWidth, barHeight, 6);
            ctx.fill();

            // Value label
            ctx.fillStyle = '#f8fafc';
            ctx.font = 'bold 12px Inter';
            ctx.textAlign = 'center';
            ctx.fillText(value, x + barWidth / 2, y - 8);

            // X label
            ctx.fillStyle = '#94a3b8';
            ctx.font = '11px Inter';
            ctx.fillText(labels[i] || '', x + barWidth / 2, rect.height - 12);
        });
    };

    window.LifeLine.drawPieChart = function(canvasId, data, labels, colors) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const dpr = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);

        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const radius = Math.min(centerX, centerY) - 30;

        const total = data.reduce((a, b) => a + b, 0);
        let currentAngle = -Math.PI / 2;

        const defaultColors = ['#e63946', '#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899'];

        data.forEach((value, i) => {
            const sliceAngle = (value / total) * Math.PI * 2;
            const color = colors?.[i] || defaultColors[i % defaultColors.length];

            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = color;
            ctx.fill();

            // Border
            ctx.strokeStyle = '#0a0e1a';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Label
            const labelAngle = currentAngle + sliceAngle / 2;
            const labelX = centerX + Math.cos(labelAngle) * (radius * 0.7);
            const labelY = centerY + Math.sin(labelAngle) * (radius * 0.7);
            
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 12px Inter';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const percent = Math.round((value / total) * 100);
            if (percent > 5) {
                ctx.fillText(`${percent}%`, labelX, labelY);
            }

            currentAngle += sliceAngle;
        });

        // Center hole for donut
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius * 0.4, 0, Math.PI * 2);
        ctx.fillStyle = '#0a0e1a';
        ctx.fill();

        // Center text
        ctx.fillStyle = '#f8fafc';
        ctx.font = 'bold 16px Inter';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('Total', centerX, centerY - 10);
        ctx.font = '14px Inter';
        ctx.fillStyle = '#94a3b8';
        ctx.fillText(total.toLocaleString(), centerX, centerY + 12);
    };

    // =========================================================
    // BLOOD TYPE EXPLORER
    // =========================================================
    window.showCompat = function(type) {
        const compatData = {
            'A+':  { donateTo: ['A+', 'AB+'], receiveFrom: ['A+', 'A-', 'O+', 'O-'] },
            'A-':  { donateTo: ['A+', 'A-', 'AB+', 'AB-'], receiveFrom: ['A-', 'O-'] },
            'B+':  { donateTo: ['B+', 'AB+'], receiveFrom: ['B+', 'B-', 'O+', 'O-'] },
            'B-':  { donateTo: ['B+', 'B-', 'AB+', 'AB-'], receiveFrom: ['B-', 'O-'] },
            'AB+': { donateTo: ['AB+'], receiveFrom: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] },
            'AB-': { donateTo: ['AB+', 'AB-'], receiveFrom: ['A-', 'B-', 'AB-', 'O-'] },
            'O+':  { donateTo: ['A+', 'B+', 'AB+', 'O+'], receiveFrom: ['O+', 'O-'] },
            'O-':  { donateTo: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], receiveFrom: ['O-'] }
        };

        const data = compatData[type];
        const panel = document.getElementById('compatPanel');
        const btns = document.querySelectorAll('.blood-type-btn');
        btns.forEach(b => b.classList.remove('active'));
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        }

        if (!panel) return;

        const special = type === 'O-' ? 'Universal Donor — can donate to all blood types!' :
                        type === 'AB+' ? 'Universal Recipient — can receive from all blood types!' :
                        type === 'AB-' ? 'Rare type — only 1% of the population.' :
                        type === 'O+' ? 'Most common blood type — 37% of the population.' : '';

        panel.innerHTML = `
            <h3>Blood Type ${type}</h3>
            <div class="compat-section-title">Can Donate To</div>
            <div class="compat-list">${data.donateTo.map(t => `<span class="compat-tag">${t}</span>`).join('')}</div>
            <div class="compat-section-title">Can Receive From</div>
            <div class="compat-list">${data.receiveFrom.map(t => `<span class="compat-tag">${t}</span>`).join('')}</div>
            ${special ? `<div style="margin-top:20px;padding:16px;background:rgba(230,57,70,0.08);border-radius:12px;border:1px solid rgba(230,57,70,0.2);font-size:0.9rem;color:var(--crimson-light);">
                <strong style="color:var(--crimson-light);">&#11088; ${special}</strong>
            </div>` : ''}
        `;
    };

    // =========================================================
    // INITIALIZE ALL
    // =========================================================
    function init() {
        initParticles();
        initHeaderScroll();
        initMobileMenu();
        initCounters();
        initScrollAnimations();
        initTabs();
        initModals();
        initNotificationDropdown();
        initSmoothScroll();

        // Convert flash messages to toasts
        const flashAlert = document.querySelector('.alert');
        if (flashAlert) {
            const type = flashAlert.classList.contains('alert-success') ? 'success' :
                        flashAlert.classList.contains('alert-danger') ? 'error' :
                        flashAlert.classList.contains('alert-warning') ? 'warning' : 'info';
            const message = flashAlert.textContent.trim();
            if (message) {
                window.LifeLine.toast(message, type, 6000);
            }
            flashAlert.style.display = 'none';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
