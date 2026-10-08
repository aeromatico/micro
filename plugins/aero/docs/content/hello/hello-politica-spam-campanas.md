---
title: Política de WhatsApp Cloud API sobre campañas comerciales y spam
sort: 85
---
# Política de WhatsApp Cloud API sobre campañas comerciales y spam

WhatsApp Cloud API (la API oficial de Meta) está diseñada para proteger la experiencia de las personas. A diferencia del correo o del SMS tradicional, donde quien consigue un número puede saturar una bandeja, en WhatsApp **es el usuario quien decide quién le escribe**. Esta página resume las reglas que Meta aplica, qué se considera spam, cómo se sanciona y cómo trabajar con **Hello** para que tus campañas sean rentables y no pongan en riesgo tu número.

> [!IMPORTANT]
> Estas reglas aplican al canal **WhatsApp Cloud API**. Si conectaste tu número por **WhatsApp Web** (QR), no existen plantillas ni ventana de 24 h, pero el riesgo de bloqueo del número es mayor, porque es una vía no oficial y los reportes de spam de los usuarios llegan igualmente a WhatsApp. Compara ambos en [WhatsApp Web vs Cloud API](hello-comparativa).

## 1. Principio base: atracción, no interrupción

WhatsApp es un canal de **atracción**: el cliente espera tu mensaje porque te dio permiso. No es un canal para interrumpir a desconocidos.

- **Consentimiento previo obligatorio (opt-in).** No puedes enviar mensajes comerciales o de marketing a alguien que no haya aceptado de forma explícita recibir comunicaciones tuyas por WhatsApp. Está prohibido usar bases de datos compradas, alquiladas, robadas o raspadas de internet.
- **Bloquear es inmediato.** Con un solo toque el usuario puede bloquear o reportar tu número. Meta mide esa reacción y castiga con rapidez a quien envía contenido irrelevante.

## 2. Una campaña comercial saludable: las 3 "C"

| Principio | Qué significa en la práctica |
|---|---|
| **Consentimiento** | El destinatario se registró para recibir el mensaje (ofertas, alertas de stock, estado de su pedido, recordatorios). Guarda cuándo y cómo lo aceptó. |
| **Contexto y relevancia** | Nada de mensajes masivos genéricos. Segmenta tu base y envía la oferta adecuada a la persona adecuada en el momento oportuno. |
| **Control** | Cada mensaje comercial ofrece una salida clara y sencilla ("Dar de baja", "No recibir más"). Respetarla evita que el usuario te reporte como spam. |

### Cómo obtener un opt-in válido

Un consentimiento es válido cuando la persona:

1. Sabe que se trata de **mensajes por WhatsApp** (no basta un consentimiento genérico de "contacto").
2. Sabe **quién** le escribirá (tu empresa) y **qué tipo** de mensajes recibirá.
3. Dio su permiso con una acción clara: casilla sin marcar de antemano en un formulario web, un mensaje que te envió ella misma por WhatsApp, un botón en tu tienda o una autorización firmada en tu local.

> [!WARNING]
> Haber obtenido el número de teléfono (tarjeta de presentación, un pedido, una consulta por otro medio) **no equivale** a consentimiento para campañas de marketing por WhatsApp.

## 3. Spam vs. campaña saludable

| Característica | Spam (prohibido) | Campaña saludable (permitida) |
|---|---|---|
| **Origen de los datos** | Bases compradas, robadas o raspadas. | Clientes propios que dejaron sus datos voluntariamente. |
| **Frecuencia** | Envíos masivos e insistentes, a horas inapropiadas. | Envíos moderados, oportunos y basados en el comportamiento del usuario. |
| **Contenido** | Ofertas agresivas, genéricas, enlaces sospechosos o engañosos. | Contenido personalizado y útil: promociones segmentadas, recordatorios. |
| **Aprobación de Meta** | Mensajes libres desde apps no oficiales (riesgo alto de bloqueo). | Plantillas oficiales preaprobadas por Meta. |
| **Opción de salida** | Difícil darse de baja; el usuario termina bloqueando. | Botón oficial o instrucción clara para cancelar con un clic. |

## 4. Reglas técnicas de la API oficial

### Ventana de atención de 24 horas y plantillas

- Cuando el usuario te escribe, se abre una **ventana de 24 horas** (contada desde su último mensaje). Dentro de ella puedes responder con mensajes libres.
- **Fuera de la ventana**, o para iniciar tú la conversación, solo puedes enviar **plantillas de mensaje aprobadas por Meta**.
- Las plantillas tienen una **categoría** (Marketing, Utilidad, Autenticación). Si declaras una categoría que no corresponde al contenido, Meta puede **reclasificarla** o rechazarla. Una plantilla de utilidad que en realidad promociona productos es un motivo frecuente de sanción.

Cómo crear y sincronizar plantillas: [Plantillas](hello-templates). Cómo Redactar te avisa cuando estás fuera de ventana: [Redactar](hello-compose).

### Calidad del número: el "semáforo"

Meta califica cada número como **alta (verde)**, **media (amarilla)** o **baja (roja)**. La calificación se basa en la retroalimentación reciente de los usuarios:

- Bloqueos y reportes de spam (lo que más pesa).
- Falta de interacción con tus mensajes.
- Motivos de reporte indicados por las personas.

### Límite de envío

Meta limita a cuántas personas distintas puedes escribirles **por tu iniciativa** (fuera de una ventana de atención) en un periodo móvil de 24 horas. El límite se define **por portafolio comercial** y lo comparten todos sus números. Los niveles vigentes son **250** (portafolios nuevos), **2.000**, **10.000**, **100.000** e **ilimitado**.

- Para pasar de 250 a 2.000 debes **verificar tu empresa** en Meta, o enviar 2.000 mensajes entregados con plantillas de alta calidad a números únicos en 30 días.
- Desde 2.000, el límite sube un nivel de forma automática (en unas 6 horas) si tus mensajes son de alta calidad y usaste al menos la mitad del límite actual en los últimos 7 días.
- Con mala calidad el límite deja de crecer; Meta puede además aplicar las restricciones de la sección siguiente.

> [!NOTE]
> Los niveles, plazos y tarifas los define Meta y cambian con el tiempo. Confirma siempre los valores vigentes en la documentación oficial (enlaces al final) antes de planificar un volumen alto.

### Sectores prohibidos o restringidos

Meta no permite usar WhatsApp para comprar, vender o promover productos o servicios ilegales, y restringe categorías como armas, tabaco, narcóticos, bienes médicos, apuestas en línea, servicios para adultos, citas, esquemas piramidales y préstamos de alto interés o cobranza de deudas. Algunas categorías reguladas (alcohol, medicamentos sin receta, juegos de azar) solo se permiten en la plataforma Business en países y con condiciones concretas.

## 5. Sanciones por incumplir

Si tu cuenta infringe la política, clasifica mal sus plantillas o acumula muchos bloqueos, Meta aplica medidas **graduales**:

| Medida | Qué pasa |
|---|---|
| **Reducción del límite** | Con calidad baja sostenida (en la práctica, más de unos 7 días), Meta puede reducir tu capacidad de envío diaria. |
| **Bloqueo temporal** | Restricciones de 1, 3, 5, 7 o hasta 30 días para enviar plantillas (marketing, utilidad o autenticación) y para agregar nuevos números. |
| **Pausa o eliminación de plantillas** | Se suspenden las plantillas con bajo rendimiento o muchos reportes. |
| **Inhabilitación permanente** | Suspensión definitiva de la cuenta de WhatsApp Business y del número asociado, en infracciones graves o reiteradas. |

Para el **mal uso de categorías de plantilla**, Meta documenta una escalada: advertencia → limitación de envío (mínimo 7 días) → restricción de plantillas de utilidad (7 días, o 30 si reincides) → restricción de todo el portafolio (30 días).

> [!CAUTION]
> Una cuenta inhabilitada por spam rara vez se recupera. Las apelaciones dependen de Meta y no hay garantía de reversión. Prevenir cuesta mucho menos que apelar.

## 6. Lista de verificación antes de lanzar una campaña

- [ ] Todos los destinatarios dieron **opt-in específico para WhatsApp** y puedes demostrarlo.
- [ ] Segmentaste la lista: cada persona recibe algo relevante para ella.
- [ ] La plantilla usa la **categoría correcta** y está **aprobada**.
- [ ] El mensaje deja claro quién eres y ofrece una forma sencilla de **darse de baja**.
- [ ] Envías en horario razonable para el destinatario y con frecuencia moderada.
- [ ] No hay enlaces acortados sospechosos ni promesas engañosas.
- [ ] Empiezas con un lote pequeño y revisas la calidad del número antes de ampliar.
- [ ] Retiras de inmediato de tus listas a quien pide la baja o te bloquea.

## 7. Buenas prácticas con Hello

- **Registra el consentimiento.** Guarda en tus contactos la fecha y el origen del permiso; en [Contactos](hello-contacts) puedes organizar tus listas por origen.
- **Revisa antes de confirmar.** El envío masivo desde [Redactar](hello-compose) sale tal cual; confirma destinatarios y plantilla antes de enviar.
- **Atiende las bajas de inmediato.** Si alguien responde "baja", "stop" o similar, sácalo de tus listas de marketing y no vuelvas a escribirle campañas.
- **Prioriza conversaciones.** Responder rápido dentro de la ventana de 24 h mejora la interacción y, con ella, la calidad del número.
- **Escala poco a poco.** Aumenta el volumen por etapas; un salto brusco con una lista fría es la forma más rápida de recibir bloqueos.
- **Usa el canal adecuado.** Para campañas a gran escala y con riesgo cero de bloqueo por vía no oficial, usa Cloud API; reserva WhatsApp Web para conversaciones uno a uno.

> [!TIP]
> Mensajes transaccionales o de utilidad (estado de un pedido, confirmación de reserva, recordatorio de cita o de pago que el cliente espera) suelen tener mucha mejor recepción que las promociones y casi no generan reportes.

## 8. El beneficio de hacerlo bien

Cuando respetas las reglas, WhatsApp se convierte en uno de los canales más rentables. El correo electrónico promedio ronda un 15 %–20 % de apertura; las comunicaciones bien segmentadas y consentidas por WhatsApp suelen superar con holgura el 90 % de apertura, precisamente porque las personas confían en la aplicación. Esa confianza es lo que Meta protege, y lo que se pierde con una sola campaña de spam.

## Referencias oficiales

Meta (consultadas en octubre de 2026):

- [WhatsApp Business Messaging Policy](https://whatsappbusiness.com/policy/) — opt-in, spam, respeto de las bajas y bienes prohibidos.
- [Messaging limits](https://developers.facebook.com/documentation/business-messaging/whatsapp/messaging-limits) — niveles de límite y cómo se escalan.
- [Template categorization](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-categorization) — Marketing, Utilidad y Autenticación; reclasificación y sanciones por mal uso.
- [Template quality rating](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-quality) — verde, amarillo, rojo; pausa y deshabilitación de plantillas.
- [Template overview and review](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview) — creación y revisión de plantillas.
- [Índice de la documentación de WhatsApp Business Platform](https://developers.facebook.com/documentation/business-messaging/whatsapp/llms.txt) — para ubicar cualquier otra página.
- [Business Support Home](https://business.facebook.com/business-support-home/) — revisiones y apelaciones.

En Hello: [Plantillas](hello-templates), [Redactar](hello-compose), [Contactos](hello-contacts), [WhatsApp Web vs Cloud API](hello-comparativa).

**Versión documentada:** 1.29.0
