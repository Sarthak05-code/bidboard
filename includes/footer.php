    <!-- Page footer -->
    <footer style="border-top: 1px solid var(--border); margin-top: 4rem; padding: 1.5rem 1.25rem; text-align: center;">
        <p class="text-sm text-muted">BidBoard &mdash; Freelance Task Marketplace</p>
    </footer>

    <script>
    // Disable submit buttons after click to prevent accidental double-submits
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        // Skip if the form already marked the button (e.g. custom handlers)
        const btn = form.querySelector('button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])');
        if (!btn) return;

        btn.disabled = true;
        if (btn.tagName === 'BUTTON') {
            if (!btn.dataset.originalText) {
                btn.dataset.originalText = btn.textContent;
            }
            btn.textContent = 'Please wait…';
        } else if (btn.tagName === 'INPUT') {
            if (!btn.dataset.originalText) {
                btn.dataset.originalText = btn.value;
            }
            btn.value = 'Please wait…';
        }

        // Re-enable after 8s as a safety net (e.g. if validation or network fails)
        setTimeout(function () {
            btn.disabled = false;
            if (btn.dataset.originalText) {
                if (btn.tagName === 'BUTTON') {
                    btn.textContent = btn.dataset.originalText;
                } else {
                    btn.value = btn.dataset.originalText;
                }
            }
        }, 8000);
    });
    </script>

</body>
</html>
