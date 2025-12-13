<template>
    <div id="redoc-container"></div>
</template>

<script>
export default {
    mounted() {
        const script = document.createElement('script');
        script.src = '/js/redoc.standalone.js';
        script.onload = () => {
            Redoc.init(
                '/api-docs/openapi.yaml',
                {
                    scrollYOffset: 0,
                    hideDownloadButton: true,
                    nativeScrollbars: true
                },
                document.getElementById('redoc-container')
            );
        };
        document.head.appendChild(script);

        // Robust scroll handler with polling
        const handleScroll = () => {
             const rawHash = location.hash.substring(1);
            if (!rawHash) return;

            const id = decodeURIComponent(rawHash);

            // Poll for the element as Redoc might render async
            let attempts = 0;
            const maxAttempts = 20; // 2 seconds (100ms * 20)

            const attemptScroll = () => {
                // Redoc might duplicate IDs (sidebar + content).
                // Query all matching IDs.
                let target = null;
                try {
                    const selector = `[id="${CSS.escape(id)}"]`;
                    const elements = document.querySelectorAll(selector);

                    if (elements.length > 1) {
                        // Prefer elements with class starting with 'sc-' which Redoc uses for Styled Components in content
                        // The 'sc-dcJsrY' class was observed on the correct content wrapper.
                        // We filter for it or simply pick the one with class containing 'sc-' if unique, or default to last.
                        target = Array.from(elements).find(el => el.className.includes('sc-dcJsrY'));

                        if (!target) {
                           // Fallback: Pick the last element, or the one with longest innerHTML
                           target = Array.from(elements).reduce((a, b) => a.innerHTML.length > b.innerHTML.length ? a : b);
                        }
                    } else if (elements.length === 1) {
                        target = elements[0];
                    }
                } catch (e) {
                    // Fallback for CSS.escape issues or invalid selectors
                    target = document.getElementById(id);
                }

                if (target) {
                    // Use 'auto' to ensure immediate scroll and avoid smooth scroll interruptions
                    target.scrollIntoView({ behavior: 'auto', block: 'start' });
                    // Also try window scroll if scrollIntoView fails in some contexts
                    // const y = target.getBoundingClientRect().top + window.pageYOffset - 50;
                    // window.scrollTo({top: y, behavior: 'auto'});
                } else if (attempts < maxAttempts) {
                    attempts++;
                    setTimeout(attemptScroll, 100);
                }
            };

            attemptScroll();
        };

        window.addEventListener('hashchange', handleScroll);

        // Initial check in case loaded with hash
        setTimeout(handleScroll, 500);
    }
}
</script>

<style scoped>
#redoc-container {
    margin: 0;
    padding: 0;
}
</style>
