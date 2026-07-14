# Diseño del vídeo promocional de Rueda Quiz

Fecha: 2026-07-14

## Objetivo

Crear un vídeo promocional de exactamente 10 segundos que presente Rueda Quiz como un juego de preguntas enérgico, competitivo y social. La pieza debe comunicar el producto aunque se reproduzca sin sonido y mostrar elementos visuales reconocibles del proyecto real.

## Formato

- Resolución: 1920 × 1080 píxeles.
- Relación de aspecto: 16:9 horizontal.
- Duración: 10 segundos.
- Entrega: MP4 H.264 a 30 fps.
- Audio: base rítmica instrumental y efectos breves; sin locución.

## Identidad visual

La composición parte de la interfaz y los recursos actuales del proyecto.

- Fondo principal: azul marino `#031a46`.
- Texto principal: blanco `#ffffff`.
- Acentos: dorado `#e7bd5b` y azul `#0d4fea`.
- Categorías: azul, verde, amarillo, naranja, rojo y morado según la paleta existente.
- Tipografía: sans serif pesada, limpia y de alta legibilidad, coherente con la interfaz.
- Recursos principales: tablero circular, quesito, dados y capturas o fragmentos de la interfaz real.

No se usarán estilos genéricos ajenos al producto, degradados oscuros a pantalla completa ni texto pequeño.

## Estructura narrativa y tiempos

### 0,0–2,0 s — Desafío

Un destello dorado revela el tablero sobre el fondo azul marino. El tablero entra con rotación controlada y ligera ampliación. Aparece el texto «¿Cuánto sabes?» con un golpe visual claro.

### 2,0–6,0 s — Juego

El dado, el quesito y los seis colores de categoría entran en una secuencia rápida. Se muestran dos vistas breves y legibles: partida local y sala online. El movimiento mantiene la atención en los elementos de juego, no en detalles pequeños de la interfaz.

### 6,0–8,5 s — Promesa

El tablero completo ocupa el centro. Aparece el mensaje «Juega. Compite. Conquista los 6 quesitos.» mediante tres entradas encadenadas. La imagen debe quedar suficientemente estable para poder leerla.

### 8,5–10,0 s — Marca

Cierre limpio con «Rueda Quiz» y el lema «Pon a prueba tus conocimientos». El último fotograma mantiene la marca completamente visible.

## Movimiento y transiciones

- Energía alta con aceleraciones breves y aterrizajes firmes.
- Cada escena tendrá entradas propias y una transición continua hacia la siguiente; no habrá cortes secos.
- Se alternarán rotación, escala, desplazamiento y aparición por máscara para evitar repeticiones.
- La escena final permanecerá estable y no se desvanecerá antes de terminar.
- Todas las animaciones serán deterministas y controladas por la línea de tiempo de HyperFrames.

## Audio

La música será instrumental, breve y rítmica, sincronizada con las principales entradas. Los efectos marcarán el destello inicial, el dado, los impactos de color y la aparición final de la marca. El montaje seguirá siendo comprensible con el audio desactivado.

## Construcción

El vídeo se producirá como una composición HyperFrames independiente dentro del proyecto. Los recursos existentes se reutilizarán sin modificar la aplicación. La composición incluirá una identidad visual documentada, escenas temporizadas, transiciones y una línea de tiempo GSAP registrada.

## Validación y aceptación

- La duración renderizada es exactamente 10 segundos.
- El vídeo se entrega en MP4 a 1920 × 1080 y 30 fps.
- HyperFrames supera las comprobaciones de estructura, contraste y desbordamiento.
- Todos los textos son legibles y permanecen dentro del lienzo.
- El tablero, los quesitos y la identidad cromática del proyecto son reconocibles.
- El mensaje se entiende sin locución y también con el audio silenciado.
- El archivo final se reproduce correctamente y el último fotograma conserva la marca visible.
