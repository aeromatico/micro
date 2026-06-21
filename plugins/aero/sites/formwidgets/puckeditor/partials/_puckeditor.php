<?php
/** @var \Aero\Sites\FormWidgets\PuckEditor $this */

$safeJson = $puckJson
    ? htmlspecialchars($puckJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    : '';
?>
<?php if ($this->previewMode): ?>
    <div class="puck-preview prose max-w-none">
        <?= $contentValue ?>
    </div>
<?php else: ?>
    <!-- puck_data JSON — synced by AeroPuckEditor.onChange -->
    <textarea
        id="<?= $puckDataId ?>"
        name="<?= $puckDataName ?>"
        style="display:none"
    ><?= $safeJson ?></textarea>

    <!-- content HTML — generated from Puck render, captured by formBeforeSave -->
    <textarea
        id="<?= $contentId ?>"
        name="<?= $contentName ?>"
        style="display:none"
    ><?= e($contentValue) ?></textarea>

    <div
        id="<?= $editorId ?>"
        style="height:700px;overflow:hidden"
    ></div>

    <script>
    (function () {
        function isDarkMode() {
            return document.body.getAttribute('data-bs-theme') === 'dark'
                || (document.body.getAttribute('data-bs-theme') === 'auto'
                    && window.matchMedia('(prefers-color-scheme: dark)').matches);
        }

        var darkStyle = document.createElement('style');
        darkStyle.id = 'puck-dark-theme';
        darkStyle.textContent = [
            ':root {',
            '--puck-color-grey-01:#f0f0f0!important;--puck-color-grey-02:#dfdfdf!important;--puck-color-grey-03:#c0c0c0!important;',
            '--puck-color-grey-04:#9a9a9a!important;--puck-color-grey-05:#808080!important;--puck-color-grey-06:#6e6e6e!important;',
            '--puck-color-grey-07:#5e5e5e!important;--puck-color-grey-08:#484848!important;--puck-color-grey-09:#343434!important;',
            '--puck-color-grey-10:#282828!important;--puck-color-grey-11:#1e1e1e!important;--puck-color-grey-12:#161616!important;',
            '--puck-color-white:#161616!important;--puck-color-black:#f0f0f0!important;',
            '--puck-color-azure-01:#dbeafe!important;--puck-color-azure-02:#bfdbfe!important;--puck-color-azure-03:#93c5fd!important;',
            '--puck-color-azure-04:#60a5fa!important;--puck-color-azure-05:#4b8de8!important;--puck-color-azure-06:#3b74d1!important;',
            '--puck-color-azure-07:#2d5fb5!important;--puck-color-azure-08:#234a94!important;--puck-color-azure-09:#1a3870!important;',
            '--puck-color-azure-10:#132a54!important;--puck-color-azure-11:#0e1f3e!important;--puck-color-azure-12:#0a152b!important;',
            '--puck-color-green-01:#dcfce7!important;--puck-color-green-02:#bbf7d0!important;--puck-color-green-03:#86efac!important;',
            '--puck-color-green-04:#4ade80!important;--puck-color-green-05:#3cc76a!important;--puck-color-green-06:#32ae57!important;',
            '--puck-color-green-07:#289146!important;--puck-color-green-08:#1f7537!important;--puck-color-green-09:#175a28!important;',
            '--puck-color-green-10:#11431d!important;--puck-color-green-11:#0b2f14!important;--puck-color-green-12:#07200d!important;',
            '--puck-color-red-01:#fee2e2!important;--puck-color-red-02:#fecaca!important;--puck-color-red-03:#fca5a5!important;',
            '--puck-color-red-04:#f87171!important;--puck-color-red-05:#ef4444!important;--puck-color-red-06:#dc2626!important;',
            '--puck-color-red-07:#b91c1c!important;--puck-color-red-08:#991b1b!important;--puck-color-red-09:#7f1d1d!important;',
            '--puck-color-red-10:#631818!important;--puck-color-red-11:#481212!important;--puck-color-red-12:#340d0d!important;',
            '--puck-color-rose-01:#ffe4e6!important;--puck-color-rose-02:#fecdd3!important;--puck-color-rose-03:#fda4af!important;',
            '--puck-color-rose-04:#fb7185!important;--puck-color-rose-05:#f43f5e!important;--puck-color-rose-06:#e11d48!important;',
            '--puck-color-rose-07:#be123c!important;--puck-color-rose-08:#9f1239!important;--puck-color-rose-09:#881337!important;',
            '--puck-color-rose-10:#6b0f2c!important;--puck-color-rose-11:#4c0b1f!important;--puck-color-rose-12:#360816!important;',
            '--puck-color-yellow-01:#fef9c3!important;--puck-color-yellow-02:#fef08a!important;--puck-color-yellow-03:#fde047!important;',
            '--puck-color-yellow-04:#facc15!important;--puck-color-yellow-05:#eab308!important;--puck-color-yellow-06:#ca8a04!important;',
            '--puck-color-yellow-07:#a16207!important;--puck-color-yellow-08:#854d0e!important;--puck-color-yellow-09:#713f12!important;',
            '--puck-color-yellow-10:#593110!important;--puck-color-yellow-11:#42240c!important;--puck-color-yellow-12:#301a08!important;',
            'color-scheme:dark!important;',
            '}'
        ].join('');

        function applyTheme() {
            if (isDarkMode()) {
                if (!document.getElementById('puck-dark-theme')) {
                    document.head.appendChild(darkStyle);
                }
            } else {
                var el = document.getElementById('puck-dark-theme');
                if (el) el.remove();
            }
        }

        applyTheme();

        new MutationObserver(applyTheme).observe(document.body, {
            attributes: true,
            attributeFilter: ['data-bs-theme'],
        });

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);

        function mountPuck() {
            if (typeof window.AeroPuckEditor === 'undefined') {
                return setTimeout(mountPuck, 100);
            }
            var existingData = <?= $puckJson ?: 'null' ?>;
            window.AeroPuckEditor.init(
                '<?= $editorId ?>',
                '<?= $puckDataId ?>',
                '<?= $contentId ?>',
                existingData
            );
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', mountPuck);
        } else {
            mountPuck();
        }
    })();
    </script>
<?php endif ?>
