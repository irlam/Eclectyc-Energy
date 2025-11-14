/**
 * Eclectyc Energy Showcase - Interactive Features
 * Handles guided tours, navigation, and animations
 */

class ShowcaseTour {
    constructor() {
        this.sections = [
            'welcome',
            'architecture',
            'import-flow',
            'worker-demo',
            'aggregation-flow',
            'tariff-analysis',
            'carbon-intensity',
            'analytics',
            'security',
            'monitoring',
            'best-practices',
            'workflows'
        ];
        
        this.currentIndex = 0;
        this.isPlaying = false;
        this.tourInterval = null;
        this.sectionDuration = 15000; // 15 seconds per section
        
        // Audio narration support
        this.speechSynthesis = window.speechSynthesis;
        this.currentUtterance = null;
        this.audioEnabled = true;
        
        this.init();
    }
    
    init() {
        this.setupNavigation();
        this.setupTourControls();
        this.setupHashNavigation();
        this.setupAudioControls();
        this.animateWorkerConsole();
        this.updateProgress();
    }
    
    setupAudioControls() {
        // Create audio toggle button in the sidebar
        const audioToggle = document.createElement('div');
        audioToggle.className = 'audio-toggle';
        audioToggle.innerHTML = `
            <button class="btn btn-secondary" id="audioToggle">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 2l-4 4H1v4h3l4 4V2z"/>
                    <path d="M12 4c.5.5 1 1.5 1 4s-.5 3.5-1 4"/>
                </svg>
                Audio Narration: ON
            </button>
        `;
        
        const tourControls = document.querySelector('.tour-controls');
        if (tourControls) {
            tourControls.appendChild(audioToggle);
        }
        
        const audioBtn = document.getElementById('audioToggle');
        if (audioBtn) {
            audioBtn.addEventListener('click', () => this.toggleAudio());
        }
    }
    
    toggleAudio() {
        this.audioEnabled = !this.audioEnabled;
        const audioBtn = document.getElementById('audioToggle');
        
        if (this.audioEnabled) {
            audioBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 2l-4 4H1v4h3l4 4V2z"/>
                    <path d="M12 4c.5.5 1 1.5 1 4s-.5 3.5-1 4"/>
                </svg>
                Audio Narration: ON
            `;
        } else {
            audioBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 2l-4 4H1v4h3l4 4V2z"/>
                    <line x1="12" y1="6" x2="15" y2="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="15" y1="6" x2="12" y2="10" stroke="currentColor" stroke-width="2"/>
                </svg>
                Audio Narration: OFF
            `;
            this.stopNarration();
        }
    }
    
    speak(text) {
        if (!this.audioEnabled || !this.speechSynthesis) {
            return;
        }
        
        // Stop any current narration
        this.stopNarration();
        
        // Create new utterance
        this.currentUtterance = new SpeechSynthesisUtterance(text);
        this.currentUtterance.rate = 0.9; // Slightly slower for clarity
        this.currentUtterance.pitch = 1.0;
        this.currentUtterance.volume = 1.0;
        
        // Use a pleasant voice if available
        const voices = this.speechSynthesis.getVoices();
        const preferredVoice = voices.find(voice => 
            voice.lang.startsWith('en') && 
            (voice.name.includes('Female') || voice.name.includes('Samantha') || voice.name.includes('Google'))
        );
        if (preferredVoice) {
            this.currentUtterance.voice = preferredVoice;
        }
        
        this.speechSynthesis.speak(this.currentUtterance);
    }
    
    stopNarration() {
        if (this.speechSynthesis) {
            this.speechSynthesis.cancel();
        }
        this.currentUtterance = null;
    }
    
    getNarrationText(sectionId) {
        const voiceOverBox = document.querySelector(`#${sectionId} .voice-over-content`);
        if (voiceOverBox) {
            // Extract text content, removing HTML tags and extra whitespace
            return voiceOverBox.textContent.replace(/\s+/g, ' ').trim();
        }
        return '';
    }
    
    setupNavigation() {
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach((item, index) => {
            item.addEventListener('click', () => {
                this.navigateToSection(index);
                this.stopTour();
            });
        });
    }
    
    setupTourControls() {
        const startTourBtn = document.getElementById('startTour');
        const fabBtn = document.getElementById('tourFab');
        
        startTourBtn.addEventListener('click', () => this.toggleTour());
        fabBtn.addEventListener('click', () => this.toggleTour());
    }
    
    setupHashNavigation() {
        // Handle direct hash navigation
        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.substring(1);
            const index = this.sections.indexOf(hash);
            
            if (index !== -1) {
                this.navigateToSection(index);
            }
        });
        
        // Check initial hash
        const hash = window.location.hash.substring(1);
        if (hash) {
            const index = this.sections.indexOf(hash);
            if (index !== -1) {
                this.navigateToSection(index);
            }
        }
    }
    
    navigateToSection(index) {
        this.currentIndex = index;
        const sectionId = this.sections[index];
        
        // Stop any current narration when navigating
        this.stopNarration();
        
        // Update active states
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Activate current section
        const activeSection = document.getElementById(sectionId);
        const activeNavItem = document.querySelector(`[data-section="${sectionId}"]`);
        
        if (activeSection) activeSection.classList.add('active');
        if (activeNavItem) {
            activeNavItem.classList.add('active');
            activeNavItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Update URL hash
        window.history.replaceState(null, null, `#${sectionId}`);
        
        // Update progress
        this.updateProgress();
        
        // Scroll to top of content
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Trigger section-specific animations
        this.triggerSectionAnimations(sectionId);
        
        // Start audio narration if enabled and tour is playing
        if (this.isPlaying && this.audioEnabled) {
            const narrationText = this.getNarrationText(sectionId);
            if (narrationText) {
                // Small delay to let the page settle before speaking
                setTimeout(() => {
                    this.speak(narrationText);
                }, 500);
            }
        }
    }
    
    toggleTour() {
        if (this.isPlaying) {
            this.stopTour();
        } else {
            this.startTour();
        }
    }
    
    startTour() {
        this.isPlaying = true;
        this.currentIndex = 0;
        
        const startBtn = document.getElementById('startTour');
        startBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <rect x="3" y="2" width="4" height="12"/>
                <rect x="9" y="2" width="4" height="12"/>
            </svg>
            Pause Tour
        `;
        
        // Start from first section
        this.navigateToSection(0);
        
        // Auto-advance every 15 seconds
        this.tourInterval = setInterval(() => {
            this.nextSection();
        }, this.sectionDuration);
    }
    
    stopTour() {
        this.isPlaying = false;
        
        if (this.tourInterval) {
            clearInterval(this.tourInterval);
            this.tourInterval = null;
        }
        
        // Stop any playing narration
        this.stopNarration();
        
        const startBtn = document.getElementById('startTour');
        startBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M3 2l10 6-10 6V2z"/>
            </svg>
            Start Guided Tour
        `;
    }
    
    nextSection() {
        this.currentIndex++;
        
        if (this.currentIndex >= this.sections.length) {
            // Tour completed
            this.stopTour();
            this.showTourCompleteMessage();
            this.currentIndex = 0;
            return;
        }
        
        this.navigateToSection(this.currentIndex);
    }
    
    updateProgress() {
        const progress = ((this.currentIndex + 1) / this.sections.length) * 100;
        const progressBar = document.getElementById('tourProgress');
        
        if (progressBar) {
            progressBar.style.width = `${progress}%`;
        }
    }
    
    triggerSectionAnimations(sectionId) {
        switch(sectionId) {
            case 'import-flow':
                this.animateFlowDiagram();
                break;
            case 'worker-demo':
                this.animateWorkerConsole();
                break;
            case 'architecture':
                this.animateArchitectureLayers();
                break;
        }
    }
    
    animateFlowDiagram() {
        const steps = document.querySelectorAll('.flow-step');
        
        steps.forEach(step => step.classList.remove('step-active'));
        
        let currentStep = 0;
        const stepInterval = setInterval(() => {
            if (currentStep < steps.length) {
                steps[currentStep].classList.add('step-active');
                currentStep++;
            } else {
                clearInterval(stepInterval);
            }
        }, 800);
    }
    
    animateWorkerConsole() {
        const console = document.getElementById('workerConsole');
        if (!console) return;
        
        const lines = [
            { text: '[2025-11-07 13:57:26] Worker started...', delay: 0, class: '' },
            { text: '[2025-11-07 13:57:26] Checking for queued jobs...', delay: 500, class: '' },
            { text: '[2025-11-07 13:57:26] Found job: batch_abc123 (priority: high)', delay: 1000, class: 'success' },
            { text: '[2025-11-07 13:57:27] Processing: sample_data.csv (15,000 rows)', delay: 1500, class: '' },
            { text: '[2025-11-07 13:57:28] Progress: 1,000/15,000 (6.67%)', delay: 2000, class: '' },
            { text: '[2025-11-07 13:57:29] Progress: 5,000/15,000 (33.33%)', delay: 2500, class: '' },
            { text: '[2025-11-07 13:57:30] Progress: 10,000/15,000 (66.67%)', delay: 3000, class: '' },
            { text: '[2025-11-07 13:57:31] Progress: 15,000/15,000 (100%)', delay: 3500, class: 'success' },
            { text: '[2025-11-07 13:57:31] ✓ Import completed successfully!', delay: 4000, class: 'success' },
            { text: '[2025-11-07 13:57:31] Cleaning up temporary files...', delay: 4500, class: '' },
            { text: '[2025-11-07 13:57:31] Worker idle, waiting for next job...', delay: 5000, class: '' }
        ];
        
        console.innerHTML = '';
        
        lines.forEach(line => {
            setTimeout(() => {
                const lineDiv = document.createElement('div');
                lineDiv.className = `console-line ${line.class}`;
                lineDiv.textContent = line.text;
                console.appendChild(lineDiv);
                console.scrollTop = console.scrollHeight;
            }, line.delay);
        });
    }
    
    animateArchitectureLayers() {
        const layers = document.querySelectorAll('.arch-layer');
        
        layers.forEach((layer, index) => {
            layer.style.opacity = '0';
            layer.style.transform = 'translateX(-50px)';
            
            setTimeout(() => {
                layer.style.transition = 'all 0.5s ease-out';
                layer.style.opacity = '1';
                layer.style.transform = 'translateX(0)';
            }, index * 200);
        });
    }
    
    showTourCompleteMessage() {
        // Show a nice completion message
        const message = document.createElement('div');
        message.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem 3rem;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            z-index: 1000;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
        `;
        
        message.innerHTML = `
            <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; color: #10b981;">
                🎉 Tour Complete!
            </h3>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">
                You've explored all features of the Eclectyc Energy Platform
            </p>
            <button onclick="this.parentElement.remove()" style="
                padding: 0.75rem 1.5rem;
                background: linear-gradient(135deg, #10b981, #3b82f6);
                color: white;
                border: none;
                border-radius: 0.5rem;
                font-weight: 600;
                cursor: pointer;
            ">
                Close
            </button>
        `;
        
        document.body.appendChild(message);
        
        setTimeout(() => {
            message.remove();
        }, 5000);
    }
}

// Keyboard Navigation
class KeyboardNav {
    constructor(tour) {
        this.tour = tour;
        this.init();
    }
    
    init() {
        document.addEventListener('keydown', (e) => {
            // Arrow keys navigation
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                this.tour.nextSection();
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                this.tour.currentIndex = Math.max(0, this.tour.currentIndex - 1);
                this.tour.navigateToSection(this.tour.currentIndex);
            } else if (e.key === ' ' && e.target === document.body) {
                // Spacebar to toggle tour
                e.preventDefault();
                this.tour.toggleTour();
            } else if (e.key === 'Escape') {
                // Escape to stop tour
                this.tour.stopTour();
            }
        });
    }
}

// Smooth scrolling and animations
class AnimationController {
    constructor() {
        this.init();
    }
    
    init() {
        // Intersection Observer for fade-in animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        });
        
        // Observe all cards and boxes
        document.querySelectorAll('.highlight-card, .voice-over-box, .tech-item, .stat-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.5s ease-out';
            observer.observe(el);
        });
        
        // Add parallax effect to hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero');
            
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.1}px)`;
            }
        });
    }
}

// Code syntax highlighting (basic)
class CodeHighlighter {
    constructor() {
        this.init();
    }
    
    init() {
        document.querySelectorAll('code.language-php').forEach(block => {
            this.highlightPHP(block);
        });
    }
    
    highlightPHP(block) {
        let html = block.innerHTML;
        
        // Keywords
        html = html.replace(/\b(public|private|protected|function|class|return|if|else|foreach|while|try|catch|new|throw)\b/g, 
            '<span style="color: #c678dd">$1</span>');
        
        // Variables
        html = html.replace(/(\$\w+)/g, '<span style="color: #e06c75">$1</span>');
        
        // Strings
        html = html.replace(/('[^']*'|"[^"]*")/g, '<span style="color: #98c379">$1</span>');
        
        // Comments
        html = html.replace(/(\/\/[^\n]*|\/\*[\s\S]*?\*\/)/g, '<span style="color: #5c6370">$1</span>');
        
        // Numbers
        html = html.replace(/\b(\d+)\b/g, '<span style="color: #d19a66">$1</span>');
        
        block.innerHTML = html;
    }
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const tour = new ShowcaseTour();
    const keyboardNav = new KeyboardNav(tour);
    const animationController = new AnimationController();
    const codeHighlighter = new CodeHighlighter();
    
    // Add mobile menu toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    // Create mobile menu button
    if (window.innerWidth <= 768) {
        const menuBtn = document.createElement('button');
        menuBtn.innerHTML = '☰';
        menuBtn.style.cssText = `
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 101;
            background: white;
            border: none;
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            font-size: 1.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            cursor: pointer;
        `;
        
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });
        
        document.body.appendChild(menuBtn);
        
        // Close sidebar when clicking outside
        mainContent.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
        });
    }
    
    // Console log for developers
    console.log('%cEclectyc Energy Platform Showcase', 'font-size: 20px; font-weight: bold; color: #10b981');
    console.log('%cInteractive tour system loaded successfully ✓', 'color: #6b7280');
    console.log('%cKeyboard shortcuts:', 'font-weight: bold; color: #3b82f6');
    console.log('  → / ↓  : Next section');
    console.log('  ← / ↑  : Previous section');
    console.log('  Space  : Toggle guided tour');
    console.log('  Esc    : Stop tour');
});

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ShowcaseTour, KeyboardNav, AnimationController, CodeHighlighter };
}
