<?php namespace Aero\Realestate\Classes;

/**
 * Textos base de los contratos. ORIENTATIVOS: no son asesoría legal y deben ser revisados por un abogado
 * antes de usarse. Los datos que el sistema no tiene salen como línea en blanco para completar a mano.
 * Variables: ver TemplateRenderer::help().
 */
class DefaultTemplates
{
    public static function all(): array
    {
        return [
            'arras' => ['name' => 'Contrato de arras (modelo base)', 'body' => self::arras()],
            'rent' => ['name' => 'Contrato de alquiler (modelo base)', 'body' => self::rent()],
            'anticretico' => ['name' => 'Contrato de anticrético (modelo base)', 'body' => self::anticretico()],
        ];
    }

    protected static function parties(string $partyA, string $partyB): string
    {
        return '<p>En la ciudad de {{ place }}, a {{ today }}, entre: <strong>{{ owner.name }}</strong>, con C.I./NIT {{ owner.document }}, de nacionalidad {{ owner.nationality }}, '
            . 'estado civil {{ owner.civil_status }}, con domicilio en {{ owner.address }}, en adelante <strong>' . $partyA . '</strong>; y <strong>{{ client.name }}</strong>, '
            . 'con C.I./NIT {{ client.document }}, de nacionalidad {{ client.nationality }}, estado civil {{ client.civil_status }}, con domicilio en {{ client.address }}, '
            . 'en adelante <strong>' . $partyB . '</strong>; quienes convienen en celebrar el presente contrato, sujeto a las siguientes cláusulas:</p>';
    }

    protected static function property(): string
    {
        return '<h3>PRIMERA — ANTECEDENTES Y OBJETO</h3>'
            . '<p>EL PROPIETARIO declara ser titular del derecho propietario sobre el inmueble ({{ property.type }}) denominado «{{ property.title }}», ubicado en {{ property.address }}, '
            . '{{ property.zone }}, {{ property.city }}, departamento de {{ property.department }}, con una superficie de terreno de {{ property.land_area }} m² y construida de {{ property.built_area }} m², '
            . 'registrado en Derechos Reales bajo folio real / matrícula N.º {{ folio_real }}.</p>';
    }

    protected static function closing(string $a, string $b): string
    {
        return '{{ extra_clauses }}'
            . '<h3>CLÁUSULA FINAL — CONFORMIDAD</h3>'
            . '<p>Las partes declaran su conformidad con todas las cláusulas y firman el presente documento en dos ejemplares de un mismo tenor y validez, en {{ place }}, a {{ today }}.</p>'
            . '<table style="width:100%;margin-top:70px"><tr>'
            . '<td style="width:50%;text-align:center">_____________________________<br>' . $a . '<br>{{ owner.name }}<br>C.I. {{ owner.document }}</td>'
            . '<td style="width:50%;text-align:center">_____________________________<br>' . $b . '<br>{{ client.name }}<br>C.I. {{ client.document }}</td>'
            . '</tr></table>'
            . '<p style="margin-top:30px;font-size:11px;color:#555">Contrato {{ code }} · Intermediado por {{ business_name }}{{ agent_line }}</p>';
    }

    protected static function arras(): string
    {
        return '<h1 style="text-align:center">CONTRATO DE ARRAS</h1>'
            . self::parties('EL VENDEDOR', 'EL COMPRADOR') . self::property()
            . '<h3>SEGUNDA — PRECIO</h3><p>El precio total pactado por el inmueble es de <strong>{{ total_price }} {{ currency }}</strong> ({{ total_price_words }}).</p>'
            . '<h3>TERCERA — ARRAS</h3><p>EL COMPRADOR entrega en este acto, en calidad de arras, la suma de <strong>{{ amount }} {{ currency }}</strong> ({{ amount_words }}), '
            . 'que EL VENDEDOR declara recibir a su entera satisfacción. Las arras se imputarán al precio al momento de formalizar la transferencia.</p>'
            . '<h3>CUARTA — PLAZO PARA FORMALIZAR</h3><p>Las partes se comprometen a suscribir la minuta y la escritura pública de transferencia, y a inscribirla en Derechos Reales, hasta el <strong>{{ end_date }}</strong>.</p>'
            . '<h3>QUINTA — SALDO</h3><p>El saldo del precio será cancelado por EL COMPRADOR en la forma y oportunidad siguientes: ______________________________________________.</p>'
            . '<h3>SEXTA — INCUMPLIMIENTO</h3><p>Si EL COMPRADOR desiste del negocio sin causa atribuible a EL VENDEDOR, perderá las arras entregadas. Si EL VENDEDOR desiste o no puede transferir el inmueble libre de gravámenes, devolverá las arras en el doble de su valor, sin perjuicio de las acciones que correspondan.</p>'
            . '<h3>SÉPTIMA — LIBERTAD DE GRAVÁMENES</h3><p>EL VENDEDOR garantiza que el inmueble se encuentra libre de gravámenes, embargos, anotaciones preventivas y deudas por impuestos o servicios, salvo los que se detallan: ______________.</p>'
            . '<h3>OCTAVA — GASTOS</h3><p>Los gastos de la transferencia (notaría, impuestos, registro) serán asumidos por: ______________________________.</p>'
            . '<h3>NOVENA — JURISDICCIÓN</h3><p>Para todo lo no previsto se aplica el Código Civil boliviano; las controversias se someterán a los jueces competentes de {{ place }}.</p>'
            . self::closing('EL VENDEDOR', 'EL COMPRADOR');
    }

    protected static function rent(): string
    {
        return '<h1 style="text-align:center">CONTRATO DE ALQUILER</h1>'
            . self::parties('EL PROPIETARIO', 'EL INQUILINO') . self::property()
            . '<h3>SEGUNDA — CANON</h3><p>EL INQUILINO pagará por el uso del inmueble un canon mensual de <strong>{{ amount }} {{ currency }}</strong> ({{ amount_words }}), por mes adelantado, dentro de los primeros cinco días de cada mes, en: ______________________________.</p>'
            . '<h3>TERCERA — PLAZO</h3><p>El contrato tiene una duración de <strong>{{ term_months }} meses</strong>, desde el <strong>{{ start_date }}</strong> hasta el <strong>{{ end_date }}</strong>. Podrá renovarse por acuerdo escrito de las partes antes de su vencimiento.</p>'
            . '<h3>CUARTA — GARANTÍA</h3><p>EL INQUILINO entrega como garantía la suma de <strong>{{ deposit }} {{ currency }}</strong> ({{ deposit_words }}), que será devuelta al término del contrato, previa verificación del estado del inmueble y de la cancelación de servicios, descontando los daños que correspondan.</p>'
            . '<h3>QUINTA — USO</h3><p>El inmueble se destinará exclusivamente a: ______________________________. EL INQUILINO no podrá subarrendarlo ni ceder el contrato sin autorización escrita de EL PROPIETARIO.</p>'
            . '<h3>SEXTA — SERVICIOS Y MANTENIMIENTO</h3><p>Los servicios básicos y las expensas son de cargo de EL INQUILINO. Las reparaciones por desgaste normal corresponden a EL PROPIETARIO; las originadas por mal uso, a EL INQUILINO.</p>'
            . '<h3>SÉPTIMA — RESOLUCIÓN</h3><p>El incumplimiento de cualquier obligación, y en especial la falta de pago del canon por ______ meses, faculta a la parte cumplida a resolver el contrato conforme a ley.</p>'
            . '<h3>OCTAVA — DEVOLUCIÓN</h3><p>Al vencimiento EL INQUILINO devolverá el inmueble en el estado en que lo recibió, salvo el desgaste normal.</p>'
            . '<h3>NOVENA — JURISDICCIÓN</h3><p>Para todo lo no previsto se aplica el Código Civil boliviano; las controversias se someterán a los jueces competentes de {{ place }}.</p>'
            . self::closing('EL PROPIETARIO', 'EL INQUILINO');
    }

    protected static function anticretico(): string
    {
        return '<h1 style="text-align:center">CONTRATO DE ANTICRÉTICO</h1>'
            . self::parties('EL PROPIETARIO', 'EL ANTICRESISTA') . self::property()
            . '<h3>SEGUNDA — CAPITAL</h3><p>EL ANTICRESISTA entrega a EL PROPIETARIO, en este acto, la suma de <strong>{{ amount }} {{ currency }}</strong> ({{ amount_words }}) en calidad de capital del anticrético, que EL PROPIETARIO declara recibir a su entera satisfacción, sirviendo el presente como recibo.</p>'
            . '<h3>TERCERA — DERECHO DE USO Y GOCE</h3><p>En garantía y como compensación de los intereses del capital, EL ANTICRESISTA tendrá la posesión, el uso y el goce del inmueble durante la vigencia del contrato, <strong>sin pagar canon de alquiler</strong>.</p>'
            . '<h3>CUARTA — PLAZO</h3><p>El plazo es de <strong>{{ term_months }} meses</strong>, desde el <strong>{{ start_date }}</strong> hasta el <strong>{{ end_date }}</strong>. Podrá renovarse por acuerdo escrito antes del vencimiento.</p>'
            . '<h3>QUINTA — RESTITUCIÓN DEL CAPITAL</h3><p>Al vencimiento del plazo EL PROPIETARIO restituirá a EL ANTICRESISTA la totalidad del capital recibido, <strong>{{ amount }} {{ currency }}</strong>, en dinero y en una sola cuota, contra la devolución del inmueble. '
            . 'Condiciones adicionales de devolución: ______________________________________________. EL ANTICRESISTA no está obligado a entregar el inmueble mientras no se le restituya el capital.</p>'
            . '<h3>SEXTA — OBLIGACIONES DEL ANTICRESISTA</h3><p>Conservar el inmueble como un buen padre de familia, pagar los servicios básicos de su consumo, no subarrendarlo ni cederlo sin autorización escrita y permitir las inspecciones razonables de EL PROPIETARIO.</p>'
            . '<h3>SÉPTIMA — OBLIGACIONES DEL PROPIETARIO</h3><p>Garantizar el uso pacífico del inmueble, pagar los impuestos que lo graven, no constituir nuevos gravámenes sin autorización de EL ANTICRESISTA y restituir el capital en la forma pactada.</p>'
            . '<h3>OCTAVA — FORMALIZACIÓN</h3><p>Las partes se obligan a elevar este contrato a <strong>escritura pública</strong> e inscribirlo en <strong>Derechos Reales</strong> dentro de ______ días, siendo los gastos de cargo de: ______________________________.</p>'
            . '<h3>NOVENA — JURISDICCIÓN</h3><p>Para todo lo no previsto se aplica el Código Civil boliviano; las controversias se someterán a los jueces competentes de {{ place }}.</p>'
            . self::closing('EL PROPIETARIO', 'EL ANTICRESISTA');
    }
}
