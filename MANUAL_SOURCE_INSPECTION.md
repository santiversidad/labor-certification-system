# Inspección de fuentes del Manual

Actualización de conciliación: sustituye el hallazgo anterior «Excel no localizado».

El Excel fue aportado desde Descargas; la búsqueda recursiva previa en el repositorio no encontró otro candidato relacionado. Se incorporó una copia byte a byte en `backend/laravel-app/database/data/`, sin guardar ni modificar el libro.

- xlsx: `C:\Users\mondr\Downloads\manual_funciones_decreto_015_2023.xlsx`; 461,321 bytes; SHA-256 `bb3184d3e49027c58a4829c8c9a689d8873c36c4ecfa06390c8339e4580a3c7c`.
- json: `C:\Users\mondr\Downloads\manual_funciones_decreto_015_2023.json`; 1,406,345 bytes; SHA-256 `261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e`.
- pdf: `C:\Users\mondr\Downloads\DECRETO   015 DE 2023 MANUAL DE FUNCIONES.pdf`; 5,846,005 bytes; SHA-256 `18a3d6c33469f517f09064f2b9e02afa062323997279ef7390f94cf3bd428e35`.

## Excel: inspección de todas las hojas

Códigos y grados son texto (incluidos `005`, `03`); formato de celda General. No se convierten a enteros. Los vacíos `t="str"` de OOXML representan cadena vacía; el adaptador conserva `""`. El único número fuente vacío es numérico y se conserva `null`. openpyxl muestra ambos como None; la distinción se comprobó en OOXML. No se recortan espacios ni se cambian acentos.

### LEEME

9 filas totales, 8 posteriores a la primera; 2 columnas. Combinadas: ['A1:B1']. Fórmulas: 0. Filas completas repetidas: 0.

Hoja documental sin tabla de fichas: título en A1:B1 y ocho pares etiqueta/valor. Se preserva como trazabilidad; su contenido no es una instrucción del usuario.

| Fila | Etiqueta | Valor |
|---|---|---|
| 1 | EXTRACCIÓN ESTRUCTURADA - DECRETO 015 DE 2023 |  |
| 2 | Fuente | Manual de Funciones y Competencias Laborales de la Alcaldía de Villavicencio |
| 3 | Perfiles/fichas detectadas | 344 |
| 4 | Funciones esenciales extraídas | 3115 |
| 5 | Uso recomendado | Validación humana e importación posterior a PostgreSQL/Laravel. |
| 6 | Clave técnica | No identificar un perfil solo por denominación/código/grado; se asignó un Perfil ID único MF-0001 a MF-0344. |
| 7 | Advertencia | Existen perfiles repetidos por denominación, código, grado e incluso área funcional, pero con propósitos/funciones diferentes. |
| 8 | Ficha especial | El perfil del Alcalde conserva grupos constitucionales/legales y literales A-G. |
| 9 | Fidelidad | Se conservó el texto extraído del decreto; no se corrigió redacción, ortografía ni contenido normativo. |

### Perfiles_Cargo

345 filas totales, 344 posteriores a la primera; 14 columnas. Combinadas: ninguna. Fórmulas: 0. Filas completas repetidas: 0.

| Columna | Encabezado | Tipos no vacíos (cantidad) | Vacíos |
|---|---|---|---:|
| A | Perfil ID | {'str': 344} | 0 |
| B | Registro decreto | {'int': 344} | 0 |
| C | Nivel | {'str': 344} | 0 |
| D | Denominación del empleo | {'str': 344} | 0 |
| E | Código | {'str': 344} | 0 |
| F | Grado | {'str': 344} | 0 |
| G | No. de cargos | {'str': 344} | 0 |
| H | Dependencia | {'str': 344} | 0 |
| I | Cargo del jefe inmediato | {'str': 344} | 0 |
| J | Área funcional | {'str': 343} | 1 |
| K | Propósito principal | {'str': 343} | 1 |
| L | Página inicio | {'int': 344} | 0 |
| M | Página fin | {'int': 344} | 0 |
| N | Fuente | {'str': 344} | 0 |

### Funciones

3116 filas totales, 3115 posteriores a la primera; 13 columnas. Combinadas: ninguna. Fórmulas: 0. Filas completas repetidas: 0.

| Columna | Encabezado | Tipos no vacíos (cantidad) | Vacíos |
|---|---|---|---:|
| A | Perfil ID | {'str': 3115} | 0 |
| B | Registro decreto | {'int': 3115} | 0 |
| C | Nivel | {'str': 3115} | 0 |
| D | Denominación | {'str': 3115} | 0 |
| E | Código | {'str': 3115} | 0 |
| F | Grado | {'str': 3115} | 0 |
| G | Área funcional | {'str': 3107} | 8 |
| H | Orden | {'int': 3115} | 0 |
| I | Número en fuente | {'int': 3114} | 1 |
| J | Grupo/Subgrupo | {'str': 58} | 3057 |
| K | Función esencial | {'str': 3115} | 0 |
| L | Página inicio perfil | {'int': 3115} | 0 |
| M | Página fin perfil | {'int': 3115} | 0 |

### Conocimientos

2327 filas totales, 2326 posteriores a la primera; 8 columnas. Combinadas: ninguna. Fórmulas: 0. Filas completas repetidas: 0.

| Columna | Encabezado | Tipos no vacíos (cantidad) | Vacíos |
|---|---|---|---:|
| A | Perfil ID | {'str': 2326} | 0 |
| B | Registro decreto | {'int': 2326} | 0 |
| C | Denominación | {'str': 2326} | 0 |
| D | Código | {'str': 2326} | 0 |
| E | Grado | {'str': 2326} | 0 |
| F | Área funcional | {'str': 2318} | 8 |
| G | Número | {'int': 2326} | 0 |
| H | Conocimiento básico esencial | {'str': 2326} | 0 |

Los ID de perfil son únicos en Perfiles_Cargo. Funciones y Conocimientos repiten el ID como FK; no son fichas duplicadas. No hay órdenes de funciones repetidos. Texto de función repetido en tres fichas: MF-0001 (6/15), MF-0047 (1/3), MF-0154 (8/10); no se elimina.

## JSON: estructura completa

Raíz: lista de 344 objetos, sin metadata raíz. Cada ficha tiene las mismas 16 propiedades. Arrays anidados: `funciones` (3.115 objetos con `orden:int`, `numero_fuente:int|null`, `grupo:string`, `texto:string`) y `conocimientos` (2.326 objetos con `numero:int`, `texto:string`). No hay otros objetos anidados.

| Propiedad | Tipos | Vacíos |
|---|---|---:|
| perfil_id | {'str': 344} | 0 |
| registro_decreto | {'int': 344} | 0 |
| nivel | {'str': 344} | 0 |
| denominacion | {'str': 344} | 0 |
| codigo | {'str': 344} | 0 |
| grado | {'str': 344} | 0 |
| numero_cargos | {'str': 344} | 0 |
| dependencia | {'str': 344} | 0 |
| jefe_inmediato | {'str': 344} | 0 |
| area_funcional | {'str': 344} | 1 |
| proposito_principal | {'str': 344} | 1 |
| pagina_inicio | {'int': 344} | 0 |
| pagina_fin | {'int': 344} | 0 |
| funciones | {'list': 344} | 0 |
| conocimientos | {'list': 344} | 1 |
| fuente | {'str': 344} | 0 |

## Matriz documental

Obligatorio indica requisito técnico de importación/expedición, sin interpretación legal.

| Campo Manual | Campo JSON | Campo Excel | Obligatorio | Uso aplicación |
|---|---|---|---|---|
| Nivel | nivel | Nivel | Sí | Catálogo/nivel |
| Denominación del empleo | denominacion | Denominación del empleo | Sí | Cargo genérico y literal de ficha |
| Código | codigo | Código | Sí | String; preservar ceros |
| Grado | grado | Grado | Sí | String; preservar ceros |
| Número de cargos | numero_cargos | No. de cargos | Importación | Texto institucional |
| Dependencia | dependencia | Dependencia | Importación | Identidad descriptiva |
| Cargo jefe inmediato | jefe_inmediato | Cargo del jefe inmediato | Importación | Ficha |
| Área funcional | area_funcional | Área funcional | Expedición | Selección explícita/snapshot |
| Propósito | proposito_principal | Propósito principal | Expedición | Snapshot |
| Funciones esenciales | funciones[] | Funciones: Orden / Número en fuente / Grupo/Subgrupo / Función esencial | Si certificado requiere funciones | Una fila por función |
| Conocimientos | conocimientos[] | Conocimientos: Número / Conocimiento básico esencial | Array presente | JSONB íntegro |
| Competencias | Ausente | Ausente | Pendiente fuente estructurada | No inventar |
| Formación | Ausente | Ausente | Pendiente fuente estructurada | No inventar |
| Experiencia | Ausente | Ausente | Pendiente fuente estructurada | No inventar |
| Equivalencias | Ausente | Ausente | Pendiente fuente estructurada | No inventar |
| Funciones comunes | Ausente | Ausente | Pendiente fuente estructurada | Resolver y snapshot separados; impresión pendiente |

## Tres fichas distintas en ambas fuentes

### MF-0001 — Excel Perfiles_Cargo fila 2

Filas relacionadas: Funciones 2–59. Los valores de ambos DTO son estrictamente iguales. Ejemplo abreviado solo en arrays:

```json
{
  "perfil_id": "MF-0001",
  "registro_decreto": 1,
  "nivel": "Directivo",
  "denominacion": "Alcalde 005-03",
  "codigo": "005",
  "grado": "03",
  "numero_cargos": "Uno (1)",
  "dependencia": "Despacho del Alcalde",
  "jefe_inmediato": "N. A",
  "area_funcional": "Despacho del Alcalde",
  "proposito_principal": "Dirigir la Administración Municipal al logro de la misión institucional mediante la ejecución eficiente del Plan de Desarrollo, la prestación de los servicios a cargo del municipio, la promoción del orden público, el desarrollo social, político y económico del municipio y la conservación de los recursos naturales con el fin de garantizar el mejoramiento de la calidad de vida de los ciudadanos, en cumplimiento de la Constitución y las leyes.",
  "pagina_inicio": 14,
  "pagina_fin": 20,
  "funciones": {
    "cantidad": 58,
    "primera": {
      "numero_fuente": 1,
      "grupo": "DE ORDEN CONSTITUCIONAL (art. 315 de la Constitución Política de Colombia)",
      "texto": "Cumplir y hacer cumplir la Constitución, la ley, los decretos del gobierno, las ordenanzas, y los acuerdos del concejo.",
      "orden": 1
    },
    "ultima": {
      "numero_fuente": null,
      "grupo": "G) Función legal",
      "texto": "Incorporar dentro del presupuesto municipal, mediante decreto, los recursos que haya recibido el tesoro municipal como cofinanciación de proyectos provenientes de las entidades nacionales o departamentales, o de cooperación internacional y adelantar su respectiva ejecución. Los recursos aquí previstos así como los correspondientes a seguridad ciudadana provenientes de los fondos territoriales de seguridad serán contratados y ejecutados en los términos previstos por el régimen presupuestal. Una vez el ejecutivo incorpore estos recursos deberá informar al Concejo Municipal dentro de los diez (10) días siguientes.",
      "orden": 58
    }
  },
  "conocimientos": {
    "cantidad": 0,
    "ejemplo": []
  },
  "fuente": "Decreto No. 1000-24/015 de 2023 - Manual de Funciones y Competencias Laborales"
}
```

### MF-0228 — Excel Perfiles_Cargo fila 229

Filas relacionadas: Funciones 2113–2122. Los valores de ambos DTO son estrictamente iguales. Ejemplo abreviado solo en arrays:

```json
{
  "perfil_id": "MF-0228",
  "registro_decreto": 228,
  "nivel": "Técnico",
  "denominacion": "Técnico Administrativo 367-05",
  "codigo": "367",
  "grado": "05",
  "numero_cargos": "Dos (2)",
  "dependencia": "Donde se ubique el cargo",
  "jefe_inmediato": "Quien ejerza la supervisión Directa",
  "area_funcional": "Oficina Asesora Jurídica",
  "proposito_principal": "Apoyar el control de los actos jurídicos para que cumplan con la Constitución y la Ley, en prevención del daño antijurídico, siguiendo procedimientos establecidos.",
  "pagina_inicio": 478,
  "pagina_fin": 480,
  "funciones": {
    "cantidad": 10,
    "primera": {
      "numero_fuente": 1,
      "grupo": "",
      "texto": "Apoyar la implementación del sistema de gestión documental de la dependencia, teniendo en cuenta las normas vigentes y procedimientos establecidos.",
      "orden": 1
    },
    "ultima": {
      "numero_fuente": 10,
      "grupo": "",
      "texto": "Desempeñar las demás funciones que le sean asignadas por autoridad o norma competente, acordes con la naturaleza general de las funciones del empleo.",
      "orden": 10
    }
  },
  "conocimientos": {
    "cantidad": 4,
    "ejemplo": [
      {
        "numero": 1,
        "texto": "Normatividad relacionada con el sector."
      }
    ]
  },
  "fuente": "Decreto No. 1000-24/015 de 2023 - Manual de Funciones y Competencias Laborales"
}
```

### MF-0229 — Excel Perfiles_Cargo fila 230

Filas relacionadas: Funciones 2123–2128. Los valores de ambos DTO son estrictamente iguales. Ejemplo abreviado solo en arrays:

```json
{
  "perfil_id": "MF-0229",
  "registro_decreto": 229,
  "nivel": "Técnico",
  "denominacion": "Técnico Administrativo",
  "codigo": "367",
  "grado": "05",
  "numero_cargos": "Uno (1)",
  "dependencia": "Donde se ubique el cargo",
  "jefe_inmediato": "Quien ejerza la supervisión Directa",
  "area_funcional": "Oficina de Gestión del Riesgo",
  "proposito_principal": "Apoyar técnicamente los procesos y actividades que desarrolla la dependencia para el logro de la prestación de servicios de manera oportuna conforme a la normatividad vigente.",
  "pagina_inicio": 480,
  "pagina_fin": 481,
  "funciones": {
    "cantidad": 6,
    "primera": {
      "numero_fuente": 1,
      "grupo": "",
      "texto": "Administrar la correspondencia interna y externa de la dependencia, siguiendo el sistema de gestión documental de la entidad.",
      "orden": 1
    },
    "ultima": {
      "numero_fuente": 6,
      "grupo": "",
      "texto": "Desempeñar las demás funciones que le sean asignadas por autoridad o norma competente, acordes con la naturaleza general de las funciones del empleo.",
      "orden": 6
    }
  },
  "conocimientos": {
    "cantidad": 4,
    "ejemplo": [
      {
        "numero": 1,
        "texto": "Administración de bases de datos y aplicativos"
      }
    ]
  },
  "fuente": "Decreto No. 1000-24/015 de 2023 - Manual de Funciones y Competencias Laborales"
}
```

## Identidad

45 grupos repiten código/grado/denominación/área/dependencia; no se fusionan. El matching añade nivel, propósito, registro y páginas; no se decide solo por perfil_id ni código/grado. El identificador interno de BD permanece separado del source_id.

La inspección PDF es puntual, descrita en MANUAL_IMPORT_COMPARISON.md; no certifica una transcripción literal de las 667 páginas. No se utilizó OCR.
