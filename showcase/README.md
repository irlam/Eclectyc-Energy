# Eclectyc Energy Platform - Interactive Showcase

## Overview

This is a standalone, interactive web showcase for the Eclectyc Energy Management Platform. It provides a visually engaging, guided tour through the platform's architecture, features, and capabilities with "voice-over" style narrations.

## Features

✨ **Guided Auto-Tour** - Automatic progression through all sections with timing  
🎯 **Manual Navigation** - Click sidebar items to jump to any section  
⌨️ **Keyboard Shortcuts** - Navigate efficiently with arrow keys  
📱 **Responsive Design** - Works beautifully on all screen sizes  
🎬 **Animations** - Smooth transitions and interactive visualizations  
💬 **Voice-Over Narration** - Detailed explanations in conversational style  
🔄 **Live Demos** - Animated demonstrations of key workflows  

## How to Use

### Opening the Showcase

1. **Direct File Access** (Development):
   ```
   Open: showcase/index.html in your browser
   ```

2. **Via Web Server** (Recommended):
   ```bash
   # If running the main platform
   Navigate to: https://eclectyc.energy/showcase/
   
   # Or use a local server
   cd showcase
   python -m http.server 8000
   # Then open: http://localhost:8000
   ```

### Navigation Methods

#### 1. Guided Tour (Recommended for First-Time Viewers)
- Click **"Start Guided Tour"** button in the sidebar
- Sit back and watch as the showcase automatically progresses through each section
- Each section displays for 15 seconds with narration
- Tour progress shown in the progress bar

#### 2. Manual Navigation
- Click any item in the sidebar menu
- Use the floating action button (bottom-right)
- Use URL hash navigation: `#section-name`

#### 3. Keyboard Shortcuts
- **→ / ↓** - Next section
- **← / ↑** - Previous section  
- **Space** - Play/pause guided tour
- **Esc** - Stop tour

## Structure

```
showcase/
├── index.html          # Main showcase page
├── css/
│   └── showcase.css    # Styling and animations
├── js/
│   └── showcase.js     # Interactive features and tour logic
├── assets/             # Images and icons (if needed)
└── README.md           # This file
```

## Sections Included

1. **Welcome** - Platform overview and statistics
2. **System Architecture** - Four-layer architecture visualization
3. **Data Import Flow** - Step-by-step import process
4. **Background Worker** - Worker process and retry logic
5. **Data Aggregation** - Aggregation pipeline explanation
6. **Tariff Analysis** - Switching analysis demonstration
7. **Carbon Intensity** - Real-time tracking integration
8. **Analytics Engine** - Insights generation
9. **Security & Access** - Role-based access control
10. **Monitoring & Ops** - Health checks and alerts
11. **Best Practices** - Tips and optimization
12. **Advanced Workflows** - Power-user techniques

## Technical Details

### Technologies Used

- **HTML5** - Semantic markup
- **CSS3** - Modern styling with CSS Grid, Flexbox, and custom properties
- **Vanilla JavaScript** - No dependencies, pure ES6+
- **Google Fonts** - Inter (UI) and JetBrains Mono (code)

### Browser Support

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

### Performance

- **Lightweight** - < 100KB total (excluding fonts)
- **Fast Load** - No external dependencies
- **Smooth Animations** - 60fps transitions
- **Accessible** - Keyboard navigation and semantic HTML

## Customization

### Changing Tour Duration

Edit `showcase/js/showcase.js`:

```javascript
this.sectionDuration = 15000; // Change to desired milliseconds
```

### Modifying Colors

Edit CSS variables in `showcase/css/showcase.css`:

```css
:root {
    --primary: #10b981;      /* Main brand color */
    --secondary: #3b82f6;    /* Accent color */
    /* ... */
}
```

### Adding New Sections

1. Add section to HTML:
```html
<section class="content-section" id="new-section">
    <!-- Content here -->
</section>
```

2. Add navigation item:
```html
<li class="nav-item" data-section="new-section">
    <span class="nav-icon">🎨</span>
    <span>New Section</span>
</li>
```

3. Register in JavaScript:
```javascript
this.sections = [
    // ... existing sections
    'new-section'
];
```

## Development

### Local Development

```bash
# Clone the repository
cd showcase

# Open in browser
open index.html

# Or run a local server
python -m http.server 8080
```

### Making Changes

1. Edit HTML for content
2. Edit CSS for styling
3. Edit JS for interactivity
4. Test in multiple browsers
5. Check mobile responsiveness

## Deployment

### To Production Server

Simply copy the entire `showcase/` folder to your web server:

```bash
# Example: Copy to web root
cp -r showcase /var/www/html/eclectyc-energy/

# Ensure permissions
chmod -R 755 /var/www/html/eclectyc-energy/showcase
```

### Standalone Deployment

The showcase is completely standalone and can be deployed separately:

```bash
# Deploy to static hosting (Netlify, Vercel, GitHub Pages, etc.)
# Just point to the showcase/ directory
```

## Accessibility

- ✅ Semantic HTML5 elements
- ✅ ARIA labels where appropriate
- ✅ Keyboard navigation fully supported
- ✅ Color contrast meets WCAG AA standards
- ✅ Responsive design for all screen sizes

## Future Enhancements

Potential additions:

- [ ] Text-to-speech narration
- [ ] Dark mode toggle
- [ ] More interactive visualizations
- [ ] Download PDF version
- [ ] Embedded video walkthroughs
- [ ] Multi-language support
- [ ] Progress saving (localStorage)
- [ ] Share specific sections (social links)

## Support

For issues or questions:

1. Check the main [README.md](../README.md)
2. Review [STATUS.md](../STATUS.md)
3. See full documentation in `/docs` directory

## License

This showcase is part of the Eclectyc Energy Platform.  
Proprietary - All rights reserved Eclectyc Energy 2025

---

**Last Updated:** November 7, 2025  
**Version:** 1.0.0
