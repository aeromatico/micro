<?= $this->listRender() ?>

<div class="callout callout-info" style="margin-top:15px">
    <div class="header"><h3>Cómo conectar un cliente MCP</h3>
    <p>Endpoint: <code>POST <?= e(url('/api/v1/mcp')) ?></code> con la cabecera <code>Authorization: Bearer &lt;key&gt;</code>. La key solo ve los módulos que le concedas aquí <em>y</em> que estén activados en Ajustes → MCP.</p></div>
</div>
