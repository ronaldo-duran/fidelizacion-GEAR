# Fixtures de reglas de negocio

Aquí viven los números del negocio (tasas, umbrales, topes, vigencias) en
JSON. Las specs y el código los leen de aquí; nunca se repiten en prosa ni
se escriben a mano en el código.

Cuando el cliente cambie un número, se cambia el fixture y el CI muestra
qué se rompió.

Mientras el cliente no confirme un valor, el fixture usa el supuesto del
diseño y lo marca con `"_supuesto": true`. Ver el issue "Decisiones
pendientes del cliente".
