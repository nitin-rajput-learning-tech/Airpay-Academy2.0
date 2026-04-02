<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 */
$string['costcenter']='Organización';
$string['employeesearch']='Filtrar';
$string['subsubdepartment']='subLOB';
$string['msg_pwd_change']='Hola {$a->username}<br/> ¡Tu contraseña cambió correctamente!';
$string['adduser']='Agregar usuario';
$string['pluginname']='Administrar usuarios';
$string['selectrole']='Seleccionar rol';
$string['assignrole']='Asignar rol';
$string['joiningdate']='FECHA DE INSCRIPCIÓN';
$string['generaldetails']='Detalles generales';
$string['personaldetails']='Detalles personales';
$string['contactdetails']='Detalles de contacto';
$string['not_assigned']='No asignado.';
$string['address']='Dirección';
$string['usersinfo']=' {$a->username} informacion del usuario';
$string['search']='Buscar';
$string['enrolldate']='Fecha de inscripción';
$string['name']='Nombre';
$string['code']='Código';
$string['table_head']=''. get_string ('semestre', 'local_semesters'). '(Curso inscrito en)';
$string['userpicture']='Imagen de usuario';
$string['newuser']='Nuevo Usuario';
$string['createuser']='Crear usuario';
$string['edituser']='<i class="fa fa-user-plus popupstringicon" aria-hidden="true"></i>Actualizar usuario</div>';
$string['updateuser']='Actualizar usuario';
$string['role']='Rol asignado';
$string['browseusers']='Explorar usuarios';
$string['browseuserspage']='Esta página permite al usuario ver la lista de usuarios con los detalles de su perfil, que también incluye el resumen de inicio de sesión.';
$string['deleteuser']='Borrar usuario';
$string['delconfirm']='¿Estás seguro? realmente quieres borrar "{$a->name}"?';
$string['deletesuccess']='Usuario "{$a->name}" borrado exitosamente.';
$string['usercreatesuccess']='Usuario "{$a->name}" creado con éxito.';
$string['userupdatesuccess']='Usuario "{$a->name}" actualizado con éxito.';
$string['addnewuser']='Agregar nuevo usuario +';
$string['assignedcostcenteris']='{$a->label} es "{$a->value}"';
$string['emailexists']='El correo electrónico ya existe.';
$string['givevaliddob']='Dar una fecha de nacimiento válida';
$string['dateofbirth']='Fecha de nacimiento';
$string['dateofbirth_help']='El usuario debe tener una edad mínima de 20 años por hoy.';
$string['assignrole_help']='Asignar un rol al usuario en la organización seleccionada.';
$string['siteadmincannotbedeleted']='El administrador del sitio no se puede eliminar.';
$string['youcannotdeleteyourself']='No puedes borrarte a ti mismo.';
$string['siteadmincannotbesuspended']='No se puede suspender al administrador del sitio.';
$string['youcannotsuspendyourself']='No te puedes suspender.';
$string['users:manage']='Administrar usuarios';
$string['manage_users']='Administrar usuarios';
$string['users:view']='Ver usuarios';
$string['users:create']='usuarios: crear';
$string['users:delete']='usuarios: eliminar';
$string['users:edit']='usuarios: editar';
$string['infohelp']='Info / Ayuda';
$string['report']='Reporte';
$string['viewprofile']='Ver perfil';
$string['myprofile']='Mi perfil';
$string['adduserstabdes']='Esta página le permite agregar un nuevo usuario. Esta puede ser una completando todos los campos obligatorios y haciendo clic en el botón "enviar".';
$string['edituserstabdes']='Esta página le permite modificar los detalles del usuario existente.';
$string['helpinfodes']='Navegar usuario mostrará toda la lista de usuarios con sus detalles, incluido su primer y último resumen de acceso. Explorar usuarios también permite al usuario agregar nuevos usuarios.';
$string['youcannoteditsiteadmin']='No puede editar el administrador del sitio.';
$string['suspendsuccess']='Usuario "{$a->name}" suspendido con éxito.';
$string['unsuspendsuccess']='Usuario "{$a->name}" Inaugurado correctamente.';
$string['p_details']='DETALLES PERSONALES / ACADÉMICOS';
$string['acdetails']='Detalles académicos';
$string['manageusers']='Administrar usuarios';
$string['username']='Nombre de usuario';
$string['unameexists']='Nombre de usuario ya existe';
$string['open_employeeidexist']='La identificación del empleado ya existe';
$string['open_employeeiderror']='La identificación del empleado puede contener solo alplabets o números, caracteres especiales no permitidos';
$string['total_courses']='Número total de cursos';
$string['enrolled']='Número de cursos inscritos';
$string['completed']='Número de cursos completados';
$string['signature'] = "Registrar's Signature";
$string['status'] = "Status";
$string['courses'] = "Cursos";
$string['date'] = "Date";
$string['doj']='Fecha de inscripción';
$string['hcostcenter']='Organización';
$string['paddress']='DIRECCIÓN PERMANENTE';
$string['caddress']='LA DIRECCIÓN ACTUAL';
$string['invalidpassword']='Contraseña invalida';
$string['dol']='Fecha de licencia';
$string['dor']='Fecha de renuncia';
$string['serviceid']='ID de empleado';
$string['help_1']='<div class="helpmanual_table"><table class="generaltable" border="1">
<tr class="field_type_head"><td class="empty_column"></td><td class="field_type font-weight-bold" style="text-align:left;border-left:1px solid white;padding-left:50px;">Campos obligatorios</td><tr>
<th>Campo</th><th>Restricción</th>
<tr><td>organización</td><td>Proporcionar la organización</td></tr>
<tr><td>nombre de usuario</td><td>Ingrese el nombre de usuario, evite espacios adicionales.</td></tr>
<tr><td>ID de empleado</td><td>Ingrese la identificación del empleado, evite espacios adicionales.</td></tr>
<tr><td>nombre de pila</td><td>Ingrese el nombre, evite espacios adicionales.</td></tr>
<tr><td>apellido</td><td>Ingrese el apellido, evite espacios adicionales.</td></tr>
<tr><td>correo electrónico</td><td>Ingrese un correo electrónico válido (debe y debe).</td></tr>
<tr><td>Cliente</td><td>Proporcionar Cliente debe existir en hrms.</td></tr>
<tr><td>Estado del Empleado</td><td>Ingrese el estado de empleado, evite espacios adicionales.</td></tr>
<tr><td>dominio</td><td>Proporcionar dominio debe existir en hrms.</td></tr>
p<tr><td>osición</td><td>Proporcionar posición debe existir en hrms.</td></tr>
';
$string['help_2']='</td></tr>
<tr class="field_type_head"><td class="empty_column"></td><td class="field_type font-weight-bold" style="text-align:left;border-left:1px solid white;"><b  class="pad-md-l-50 hlep2-oh">Campos normales</b></td><tr>
<th>Campo</th><th>Restricción</th>
<tr><td>ciudad</td><td>Ingrese el nombre de la ciudad, evite espacios adicionales.</td></tr>
<tr><td>role_designation</td><td>Ingrese Designación de rol, evite espacios adicionales.</td></tr>
<tr><td>nivel</td><td>Ingrese al nivel, evite espacios adicionales.</td></tr>
<tr><td>dirección</td><td>Ingrese Dirección, evite espacios adicionales.</td></tr>
<tr><td>no móviles</td><td>Ingrese solo números.</td></tr>
<tr><td>nombre del Estado</td><td>Ingrese el nombre del estado, evite espacios adicionales.</td></tr>
<tr><td>reportingmanager_email</td><td>Ingrese al correo electrónico de Gerente de Reportes, evite espacios adicionales.</td></tr>
</table>';
$string['help_1_orghead']='<table class="generaltable" border="1">
<tr><td></td><td style="text-align:left;border-left:1px solid white;"><b class="pad-md-l-50 hlep1-oh">
Campos obligatorios</b></td><tr>
<th>Campo</th><th>Restricción</th>
<tr><td>nombre de usuario</td><td>Ingrese el nombre de usuario, evite espacios adicionales.</td></tr>
<tr><td>ID de empleado</td><td>Ingrese la identificación del empleado, evite espacios adicionales.</td></tr>
<tr><td>nombre de pila</td><td>Ingrese el nombre, evite espacios adicionales.</td></tr>
<tr><td>apellido</td><td>Ingrese el apellido, evite espacios adicionales.</td></tr>
<tr><td>correo electrónico</td><td>Ingrese un correo electrónico válido (debe y debe).</td></tr>
<tr><td>Cliente</td><td>Proporcionar Cliente debe existir en hrms.</td></tr>
<tr><td>Estado del Empleado</td><td>Ingrese el estado de empleado, evite espacios adicionales.</td></tr>
d<tr><td>ominio</td><td>Proporcionar dominio debe existir en hrms.</td></tr>
<tr><td>posición</td><td>Proporcionar posición debe existir en hrms.</td></tr>
';
$string['help_1_dephead']='<table class="generaltable" border="1">
<tr><td></td><td style="text-align:left;border-left:1px solid white;"><b class="pad-md-l-50 hlep1-dh">Campos obligatorios</b></td><tr>
<th>Campo</th><th>Restricción</th>
<tr><td>nombre de usuario</td><td>Ingrese el nombre de usuario, evite espacios adicionales.</td></tr>
<tr><td>ID de empleado</td><td>Ingrese la identificación del empleado, evite espacios adicionales.</td></tr>
<tr><td>nombre de pila</td><td>Ingrese el nombre, evite espacios adicionales.</td></tr>
<tr><td>apellido</td><td>Ingrese el apellido, evite espacios adicionales.</td></tr>
<tr><td>correo electrónico</td><td>Ingrese un correo electrónico válido (debe y debe).</td></tr>
<tr><td>Estado del Empleado</td><td>Ingrese el estado de empleado, evite espacios adicionales.</td></tr>
';
$string['already_assignedstocostcenter']='{$a} ya asignado a costcenter. Anule la asignación de Costcenter para continuar';
$string['already_instructor']='{$a} ya asignado como instructor. Anule la asignación de este usuario como instructor para continuar.';
$string['already_mentor']='{$a} ya asignado como mentor. Anule la asignación de este usuario como mentor para continuar.';
$string['download']='Descargar';
$string['csvdelimiter']='Delimitador CSV';
$string['encoding']='Codificación';
$string['errors']='Errores';
$string['nochanges']='Sin cambios';
$string['uploadusers']='Subir usuarios';
$string['rowpreviewnum']='Vista previa de filas';
$string['uploaduser']='Subir usuarios';
$string['back_upload']='Volver a Cargar usuarios';
$string['bulkuploadusers']='Usuarios de carga masiva';
$string['uploaduser_help']='El formato del archivo debe ser el siguiente: * Cada línea del archivo contiene un registro * Cada registro es una serie de datos separados por comas (u otros delimitadores) * El primer registro contiene una lista de nombres de campo que definen el formato del resto del archivo';
$string['uploaduserspreview']='Cargar vista previa de usuarios';
$string['userscreated']='Usuarios creados';
$string['usersskipped']='Usuarios omitidos';
$string['usersupdated']='Usuarios actualizados';
$string['uuupdatetype']='Detalles de los usuarios existentes';
$string['uuoptype']='Tipo de carga';
$string['uuoptype_addnew']='Agregue solo nuevos, omita los usuarios existentes';
$string['uuoptype_addupdate']='Agregar usuarios nuevos y actualizar los existentes';
$string['uuoptype_update']='Actualizar solo los usuarios existentes';
$string['uuupdateall']='Anular con archivo y valores predeterminados';
$string['uuupdatefromfile']='Anular con archivo';
$string['uuupdatemissing']='Complete lo que falta en el archivo y los valores predeterminados';
$string['uploadusersresult']='Resultado de usuarios subidos';
$string['helpmanual']='Descargue una hoja de Excel de muestra y complete los valores de campo en el formato especificado a continuación.';
$string['manual']='Manual de ayuda';
$string['info']='Ayuda';
$string['helpinfo']='Navegar usuario mostrará toda la lista de usuarios con sus detalles, incluido su primer y último resumen de acceso. Explorar usuarios también permite al usuario agregar nuevos usuarios.';
$string['changepassdes']='Esta página permite al usuario ver la lista de usuarios con los detalles de su perfil, que también incluye el resumen de inicio de sesión. Aquí también puede administrar (editar / eliminar / desactivar) los usuarios.';
$string['changepassinstdes']='Esta página le permite actualizar o modificar la contraseña en cualquier momento; siempre que el instructor debe proporcionar la contraseña actual correctamente.';
$string['changepassregdes']='Esta página le permite actualizar o modificar la contraseña en cualquier momento; siempre que el registrador debe proporcionar la contraseña actual correctamente.';
$string['info_help']='<h1>Explorar usuarios</h1>
Esta página permite al usuario ver la lista de usuarios con los detalles de su perfil, que también incluye el resumen de inicio de sesión. Aquí también puede administrar (editar / eliminar / desactivar) los usuarios.
<h1>Agregar nuevo / crear usuario</h1>
Esta página le permite agregar un nuevo usuario. Esta puede ser una completando todos los campos obligatorios y haciendo clic en el botón enviar.';
$string['enter_grades']='Ingrese calificaciones';
$string['firstname']='Nombre de pila';
$string['middlename']='Segundo nombre';
$string['lastname']='Apellido';
$string['female']='Hembra';
$string['male']='Masculino';
$string['userdob']='Fecha de nacimiento';
$string['phone']='Móvil';
$string['email']='Correo electrónico';
$string['emailerror']='Ingrese una ID de correo electrónico válida';
$string['phoneminimum']='Ingrese un mínimo de 10 dígitos';
$string['phonemaximum']='No puede ingresar más de 15 dígitos';
$string['country_error']='Por favor seleccione un país';
$string['numeric']='Solo valores numéricos';
$string['pcountry']='País';
$string['genderheading']='Generar rumbo';
$string['primaryyear']='Año de primaria';
$string['score']='Puntuación';
$string['contactname']='Nombre de contacto';
$string['hno']='Número de casa';
$string['phno']='Número de teléfono';
$string['pob']='Lugar de nacimiento';
$string['contactname']='Nombre de contacto';
$string['bulkassign']='Asignación masiva al centro de costos';
$string['im:costcenter_unknown']='Centro de costos desconocido';
$string['im:user_unknown']='Usuario desconocido';
$string['im:user_notcostcenter']='Administrador registrado no asignado a este centro de costos "{$a->csname}"';
$string['im:already_in']='Usuario ya asignado al centro de costos';
$string['im:assigned_ok']='{$a} Usuario asignado correctamente';
$string['upload_employees']='Subir empleados';
$string['assignuser_costcenter']='Asignar usuarios a la organización';
$string['button']='SEGUIR';
$string['idnumber']='Número de identificación';
$string['username']='Nombre de usuario';
$string['firstcolumn']='La columna de usuario contiene';
$string['enroll_batch']='Inscribirse por lotes';
$string['mass_enroll']='Inscripciones masivas';
$string['mass_enroll_help'] =<<<EOS
<h1>Bulk enrolments</h1>

<p>
With this option you are going to enrol a list of known users from a file with one account per line
</p>
<p>
<b> The firstline </b> the empty lines or unknown accounts will be skipped. </p>

<p>
The file may contains one or two columns, separated by a comma, a semi-column or a tabulation.

You should prepare it from your usual spreadsheet program from official lists of students, for example,
and add if needed a column with groups to which you want these users to be added. Finally export it as CSV. (*)</p>

<p>
<b> The first one must contains a unique account identifier </b>: idnumber (by default) login or email  of the target user. (**). </p>

<p>
The second <b>if present,</b> contains the group's name in wich you want that user to be added. </p>

<p>
If the group name does not exist, it will be created in your course, together with a grouping of the same name to which the group will be added.
.<br/>
This is due to the fact that in Moodle, activities can be restricted to groupings (group of groups), not groups,
 so it will make your life easier. (this requires that groupings are enabled by your site administrator).

<p>
You may have in the same file different target groups or no groups for some accounts
</p>

<p>
You may unselect options to create groups and groupings if you are sure that they already exist in the course.
</p>

<p>
By default the users will be enroled as students but you may select other roles that you are allowed to manage (teacher, non editing teacher
or any custom roles)
</p>

<p>
You may repeat this operation at will without dammages, for example if you forgot or mispelled the target group.
</p>


<h2> Sample files </h2>

Id numbers and a group name to be created in needed in the course (*)
<pre>
"idnumber";"group"
" 2513110";" 4GEN"
" 2512334";" 4GEN"
" 2314149";" 4GEN"
" 2514854";" 4GEN"
" 2734431";" 4GEN"
" 2514934";" 4GEN"
" 2631955";" 4GEN"
" 2512459";" 4GEN"
" 2510841";" 4GEN"
</pre>

only idnumbers (**)
<pre>
idnumber
2513110
2512334
2314149
2514854
2734431
2514934
2631955
</pre>

only emails (**)
<pre>
email
toto@insa-lyon.fr
titi@]insa-lyon.fr
tutu@insa-lyon.fr
</pre>

usernames and groups, separated by a tab :

<pre>
username	 group
ppollet      groupe_de_test              will be in that group
codet        groupe_de_test              also him
astorck      autre_groupe                will be in another group
yjayet                                   no group for this one
                                         empty line skipped
unknown                                  unknown account skipped
</pre>

<p>
<span <font color='red'>(*) </font></span>: double quotes and spaces, added by some spreadsheet programs will be removed.
</p>

<p>
<span <font color='red'>(**) </font></span>: target account must exit in Moodle ; this is normally the case if Moodle is synchronized with
some external directory (LDAP...)
</p>


EOS;

$string['reportingto']='Reportes a';
$string['functionalreportingto']='Reportes funcionales a';
$string['ou_name']='Nombre OU';
$string['department']='Cliente';
$string['costcenter_custom']='Centro de costos';
$string['subdepartment']='Sub-Cliente';
$string['designation']='Designacion';
$string['designations_help']='Busque y seleccione una designación del grupo disponible. Las designaciones disponibles aquí son las designaciones que se asignan a los usuarios en el sistema. Seleccionar una designación significa que cualquier usuario del sistema que tenga asignada la designación seleccionada será elegible para la inscripción.';
$string['client']='Cliente';
$string['grade']='Calificación';
$string['team']='Equipo';
$string['hrmrole']='Rol de HRMS';
$string['role_help'] = "Search and select a role from the available pool. Roles made available here are the roles that are mapped to users on the system. Selecting a 'role (s)' means that any user in the system who has the selected role mapped to them will be eligible for enrollment.";
$string['zone']='Zona';
$string['region']='Región';
$string['branch']='Rama';
$string['group']='Grupo';
$string['preferredlanguage']='Idioma';
$string['open_group']='Nivel';
$string['open_band']='Banda';
$string['open_role']='Papel';
$string['open_zone']='Zona';
$string['open_region']='Región';
$string['open_grade']='Calificación';
$string['open_branch']='Rama';
$string['position']='Papel';
$string['emp_status']='Estado del Empleado';
$string['resign_status']='Estado de renuncia';
$string['emp_type']='Tipo de empleado';
$string['dob']='Fecha de nacimiento';
$string['career_track_tag']='Pista de carrera';
$string['campus_batch_tag']='Lote de campus';
$string['calendar']='Nombre del calendario';
$string['otherdetails']='Otros detalles';
$string['location']='Ubicación';
$string['city']='Ciudad';
$string['gender']='Género';
$string['usersupdated']='Usuarios actualizados';
$string['supervisor']='Informar a';
$string['selectasupervisor']='Seleccione Informar a';
$string['reportingmanagerid']='Reportes funcionales a';
$string['selectreportingmanager']='Seleccione Reportes funcionales';
$string['salutation']='Saludo';
$string['employment_status']='Estado de Empleo';
$string['confirmation_date']='Fecha de confirmación';
$string['confirmation_due_date']='Fecha de vencimiento de la confirmación';
$string['age']='Años';
$string['paygroup']='Grupo de pago';
$string['physically_challenge']='Desafío físico';
$string['disability']='Discapacidad';
$string['employment_type']='Tipo de empleo';
$string['employment_status']='Estado de Empleo';
$string['employee_status']='Estado del Empleado';
$string['enrol_user']='Inscribir usuarios';
$string['level']='Nivel';
$string['select_career']='Seleccionar carrera profesional';
$string['select_grade']='Seleccionar calificación';
$string['userinfo']='Información de usuario';
$string['addtional_info']='Información adicional';
$string['user_transcript']='Transcripción del usuario';
$string['type']='Tipo';
$string['transcript_history']='Historial de la transcripción (2015-2016)';
$string['sub_sub_department']='Sub Sub Depatement';
$string['zone_region']='Región de zona';
$string['area']='Zona';
$string['dob']='DOB';
$string['matrail_status']='Estado marcial';
$string['state']='Estado';
$string['course_header']='APRENDIZAJE ACTUAL';
$string['courses_header_emp']='APRENDIZAJE ACTUAL PARA';
$string['courses_data']='No hay cursos para mostrar.';
$string['page_header']='detalles del perfil';
$string['adnewuser']='<i class="fa fa-user-plus popupstringicon" aria-hidden="true"></i> Crear usuario <div class= "popupstring"></div>';
$string['empnumber']='ID de empleado';
$string['departments']='Clientes';
$string['sub_departments']='LOBs';
$string['department_help']='Esta configuración determina la categoría en la que aparecerá el Cliente.';
$string['subdepartment_help']='Esta configuración determina la categoría en la que aparecerá el LOB en la lista de Clientes.';
$string['subsubdepartment_help']='Esta configuración determina la categoría en la que aparecerá el sub-Cliente en la lista de sub-Clientes.';
$string['errordept']='Por favor seleccione Cliente';
$string['errorsubdept']='Seleccione LOB';
$string['errorsubsubdept']='Por favor seleccione Sub Sub Cliente';
$string['errorfirstname']='Ingrese el nombre';
$string['errorlastname']='Por favor ingrese su apellido';
$string['erroremail']='Ingrese la dirección de correo electrónico';
$string['filemail']='Dirección de correo electrónico';
$string['Departments']='Cliente';
$string['Sub_Departments']='Sub-Cliente';
$string['idexits']='ID de empleado ya existe';
$string['options']='Opción';
$string['enrollmethods']='Método de inscripción';
$string['authenticationmethods']='Método de autentificación';
$string['assigned_courses']='Cursos asignados';
$string['completed_courses']='Cursos completados';
$string['not_started_courses']='No empezado';
$string['inprogress_courses']='En progreso';
$string['employee_id']='ID de empleado';
$string['certificates']='Certificados';
$string['already_assignedlp']='Usuario asignado al plan de aprendizaje';
$string['coursehistory']='Historia';
$string['employees']="Employee's";
$string['learningplans']="Rutas de aprendizaje";
$string['lowercaseunamerequired']='El nombre de usuario debe estar solo en minúsculas';
$string['sync_users']='Sincronizar usuarios';
$string['sync_errors']='Errores de sincronización';
$string['sync_stats']='Estadísticas de sincronización';
$string['view_users']='ver usuarios';
$string['nodepartmenterror']='El Cliente no puede estar vacío';
$string['syncstatistics']='Estadísticas de sincronización';
$string['phonenumvalidate']='Ingrese un número válido de 10 dígitos';
$string['cannotcreateuseremployeeidadderror']='Empleado con ID de empleado {$a->employee_id} ya existe, por lo que no se puede crear un usuario en modo adduser en la línea {$a->linenumber}';
$string['cannotfinduseremployeeidupdateerror']='Empleado con ID de empleado {$a} no existe';
$string['cannotcreateuseremailadderror']='Empleado con mailid {$a->email} ya existe, por lo que no se puede crear un usuario en modo adduser en la línea {$a->linenumber}';
$string['cannotedituseremailupdateerror']='Empleado con mailid {$a->email} no existe, por lo que no se puede actualizar en modo de actualización en la línea {$a->linenumber}';
$string['multipleuseremployeeidupdateerror']='Varios empleados con ID de empleado {$a} existe';
$string['multipleedituseremailupdateerror']='Varios empleados con correo electrónico {$a} existe';
$string['multipleedituserusernameediterror']='Varios empleados con nombre de usuario {$a} existe';
$string['cannotedituserusernameediterror']='Empleado con nombre de usuario {$a} no existe en modo de actualización';
$string['cannotcreateuserusernameadderror']='Empleado con nombre de usuario {$a->username} ya existe no se puede crear un usuario en el modo agregar en la línea {$a->linenumber}';
$string['deleteconfirm']='Estas seguro que quieres borrarlo " {$a->fullname} "empleado?';
$string['local_users_table_footer_content']='Demostración {$a->start_count} a {$a->end_count} de {$a->total_count} entradas';
$string['suspendconfirm']='¿Está seguro de que desea cambiar el estado de {$a->fullname} ?';
$string['suspendconfirmenable']='¿Estás seguro de hacer empleado \'{$a->fullname}\' inactivo?';
$string['suspendconfirmdisable']='¿Estás seguro de hacer empleado \'{$a->fullname} \'activo?';
$string['firstname_surname']='Nombre Apellido';
$string['employeeid']='ID de empleado';
$string['emailaddress']='Dirección de correo electrónico';
$string['organization']='Organización';
$string['supervisorname']='Informar a';
$string['lastaccess']='Ultimo acceso';
$string['actions']='Comportamiento';
$string['classrooms']='Aulas';
$string['onlineexams']='Exámenes online';
$string['programs']='Programas';
$string['contactno']='Contacto no';
$string['nosupervisormailfound']='No se encontraron administradores de reportes con el correo electrónico {$a->email} en línea {$a->line}.';
$string['valusernamerequired']='Ingrese un nombre de usuario válido';
$string['valfirstnamerequired']='Por favor, ingrese un nombre válido';
$string['vallastnamerequired']='Por favor ingrese un apellido válido';
$string['errororganization']='Seleccione Organización';
$string['usernamerequired']='Ingrese su nombre de usuario';
$string['passwordrequired']='Por favor, ingrese contraseña';
$string['departmentrequired']='Por favor seleccione Cliente';
$string['employeeidrequired']='Por favor ingrese ID de empleado';
$string['noclassroomdesc']='No se proporcionó descripción';
$string['noprogramdesc']='No se proporcionó descripción';
$string['team_dashboard']='Tablero del equipo';
$string['myteam']='Mi equipo';
$string['idnumber']='ID de empleado';
$string['target_audience']='Público objetivo';
$string['open_group']='Grupo';
$string['groups_help']='Busque y seleccione un grupo personalizado disponible o existente como público objetivo';
$string['open_band']='Banda';
$string['open_hrmsrole']='Rol de HRMS';
$string['role_help'] = "Search and select a role from the available pool. Roles made available here are the roles that are mapped to users on the system. Selecting a 'role (s)' means that any user in the system who has the selected role mapped to them will be eligible for enrollment.";
$string['open_branch']='Rama';
$string['open_designation']='Designacion';
$string['designation_help']='Busque y seleccione una designación del grupo disponible. Las designaciones disponibles aquí son las designaciones que se asignan a los usuarios en el sistema. Seleccionar una designación significa que cualquier usuario del sistema que tenga asignada la designación seleccionada será elegible para la inscripción.';
$string['open_location']='Ubicación';
$string['location_help'] = "Users belonging to these location can enrol/request to this modulSearch and select an available or existing employee location's. The location available here are the locations that are mapped to users on the system. Selecting a location(s) means that any user in the system who has the selected location mapped to them will be eligible for enrollment.";
$string['team_allocation']='Asignación de equipos';
$string['myteam']='Mi equipo';
$string['allocate']='Asignar';
$string['learning_type']='Tipo de aprendizaje';
$string['team_confirm_selected_allocation']='¿Confirmar asignación?';
$string['team_select_user']='Seleccione un usuario.';
$string['team_select_course_s']='Seleccione cursos válidos.';
$string['team_approvals']='Aprobaciones del equipo';
$string['approve']='Aprobar';
$string['no_team_requests']='No hay solicitudes del equipo';
$string['team_no_learningtype']='Seleccione cualquier tipo de aprendizaje.';
$string['select_requests']='Seleccione cualquier solicitud.';
$string['select_learningtype']='Seleccione cualquier tipo de aprendizaje.';
$string['allocate_search_users']='Buscar usuarios ...';
$string['allocate_search_learnings']='Buscar tipos de aprendizaje ...';
$string['select_user_toproceed']='Seleccione un usuario para continuar.';
$string['no_coursesfound']='No se encontraron cursos';
$string['no_classroomsfound']='No se encontraron aulas';
$string['no_programsfound']='No se encontraron programas';
$string['team_requests_search']='Solicitudes de equipo de búsqueda por usuarios ...';
$string['team_nodata']='No se encontraron registros';
$string['allocate_confirm_allocate']='¿Está seguro de que desea aprobar las solicitudes seleccionadas?';
$string['team_request_confirm']='¿Está seguro de que desea aprobar las solicitudes seleccionadas?';
$string['members']='Miembros';
$string['permissiondenied']='No tienes permisos para ver esta página.';
$string['onlinetests']='Pruebas online';
$string['manage_br_users']='Administrar<br/> usuarios';
$string['profile']='Perfil';
$string['badges']='Insignias';
$string['completed']='Completado';
$string['notcompleted']='no completado';
$string['nopermission']='No tienes permisos para ver esta página.';
$string['selectdepartment']='Seleccionar Cliente';
$string['selectsupervisor']='Seleccione Informar a';
$string['total']='Total';
$string['active']='Activos';
$string['inactive']='Inactivos';
$string['deleteconfirmsynch']='¿Está seguro de que desea eliminar los valores seleccionados?';
$string['classroom']='Aulas';
$string['learningplan']='Plan de aprendizaje';
$string['program']='Programa';
$string['open_level']='Nivel';
$string['certification']='Certificación';
$string['certifications']='Certificaciones';
$string['groups']='grupos';
$string['notbrandedmobileapp']='No está utilizando la aplicación móvil de la marca BizLMS';
$string['makeactive']='Hacer Activo';
$string['makeinactive']='Hacer Inactivo';
$string['position']='Posición';
$string['positionreq']='Seleccionar rol';
$string['domain']='Dominio';
$string['domainreq']='Seleccionar dominio';
$string['skillname']='nombre de la habilidad';
$string['level']='Nivel';
$string['categorypopup']='Competencia {$a}';
$string['competency']='Competencia';
$string['skill_profile']='Perfil de habilidad';
$string['competency']='Competencia';
$string['skills']='Habilidades';
$string['open_level']='Nivel';
$string['competencyprogress']='Progreso de la competencia';


$string['login']='Iniciar sesión';
$string['users']='Usuarios';
$string['selectonecheckbox_msg']='Seleccione al menos una casilla de verificación';
$string['save_continue']='Guardar Continuar';
$string['skip']='Omitir';
$string['previous']='Anterior';
$string['cancel']='Cancelar';
$string['emailaleadyexists']='Usuario con correo electrónico
{$a->email}
ya existe en la línea
{$a->excel_line_number}
.';
$string['usernamealeadyexists']='Usuario con correo electrónico
{$a->email}
ya existe en la línea
{$a->excel_line_number}
.';
$string['employeeid_alreadyexists']='Usuario con identificación de empleado
{$a->employee_id) already exist at line
{$a->excel_line_number}
.
';
$string['empiddoesnotexists']='Usuario con identificación de empleado
{$a->employee_id) does not exist at line
{$a->excel_line_number}
.
';
$string['empfile_syncstatus']='Estado de sincronización de archivos de empleados';
$string['addedusers_msg']='Total
{$a}
nuevos usuarios agregados al sistema.';
$string['updatedusers_msg']='Total
{$a}
datos de usuarios actualizados.';
$string['errorscount_msg']='Total
{$a}
Se produjeron errores en la actualización de sincronización.';
$string['warningscount_msg']='Total
{$a}
Se produjeron advertencias en la actualización de sincronización.';
$string['superwarnings_msg']='Total
{$a}
Se produjeron advertencias al actualizar el supervisor.';
$string['filenotavailable']='El archivo con datos de empleados no está disponible por hoy.';
$string['orgmissing_msg']='Proporcione la información de la organización para la identificación del empleado
{$a->employee_id}
de hoja cargada en la línea
{$a->excel_line_number}
';
$string['invalidorg_msg']='Organización "
{$a->org_shortname}
"para identificación de empleado"
{$a->employee_id}
"en la hoja de Excel cargada no existe en el sistema en la línea
{$a->excel_line_number}
';
$string['otherorg_msg']='Organización "
{$a->org_shortname}
"ingresado en la línea"
{$a->employee_id}
"para identificación de empleado"
{$a->excel_line_number}
"en la hoja de Excel cargada no le pertenece.';
$string['invaliddept_msg']='Cliente "
{$a->dept_shortname}
"para identificación de empleado"
{$a->employee_id}
"en la hoja de Excel cargada no existe en el sistema en la línea
{$a->excel_line_number}
';
$string['otherdept_msg']='Cliente "
{$a->dept_shortname}
"ingresado en la línea
{$a->excel_line_number}
para identificación de empleado "
{$a->employee_id}
"en la hoja de Excel cargada no le pertenece.';
$string['invalidempid_msg']='Proporcione un valor de identificación de empleado válido
{$a->employee_id}
insertado en la hoja de Excel en la línea
{$a->excel_line_number}
.';
$string['empidempty_msg']='Proporcione la identificación de empleado para el nombre de usuario "
{$a->username}
"de hoja cargada en la línea
{$a->excel_line_number}
.';
$string['error_employeeidcolumn_heading']='Error en el encabezado de la columna de identificación del empleado en la hoja de Excel cargada';
$string['firstname_emptymsg']='Proporcione el nombre de la identificación del empleado "
{$a->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
.';
$string['error_firstnamecolumn_heading']='Error en el encabezado de la columna de nombre en la hoja de Excel cargada';
$string['latname_emptymsg']='Proporcione el apellido para la identificación del empleado "
{$excel->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
';
$string['error_lastnamecolumn_heading']='Error en el encabezado de la columna de apellido en la hoja de Excel cargada';
$string['email_emptymsg']='Proporcione la identificación de correo electrónico para la identificación del empleado "
{$a->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
';
$string['invalidemail_msg']='Se ha introducido un ID de correo electrónico no válido para el ID de empleado "
{$a->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
.';
$string['columnsarragement_error']='Error en la disposición de las columnas en la hoja de Excel cargada en la línea
{$a}
';
$string['invalidusername_error']='Proporcione un nombre de usuario válido para la identificación del empleado "
{$a->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
';
$string['usernameempty_error']='Proporcione el nombre de usuario para la identificación del empleado "
{$a->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
';
$string['empstatusempty_error']='Proporcione el estado del empleado para la identificación del empleado "
{$a->employee_id}
"de la hoja de Excel cargada en la línea
{$a->excel_line_number}
';
$string['select_org']='--Seleccione Organización--';
$string['select_dept']='--Seleccione Cliente--';
$string['select_reportingto']='--Seleccione Reportando a--';
$string['select_domain']='--Seleccione Dominio--';
$string['select_role']='--Seleccione Rol--';
$string['select_position']='--Seleccione Posición--';
$string['select_subdept']='--Seleccione LOB--';
$string['select_opt']='-- Seleccione --';
$string['only_add']='Solo agregar';
$string['only_update']='Solo actualizar';
$string['add_update']='Tanto agregar como actualizar';
$string['disable']='Inhabilitar';
$string['enable']='Habilitar' ;
$string['employee']='Empleado ';
$string['error_in_creation']='Error en la creación ';
$string['error_in_inactivating']='Error al inactivar';
$string['error_in_deletion']='Error al borrar';
$string['file_notfound_msg']='archivo no encontrado / error de archivo vacío';
$string['back']='atrás';
$string['sample']='Muestra';
$string['help_manual']='Manual de ayuda';
$string['sync_errors']='Errores de sincronización';
$string['welcome']='Bienvenido';
$string['edit_profile']='Editar perfil';
$string['messages']='Mensajes';
$string['competencies']='Competencias';
$string['error_with']='Error con';
$string['uploaded_by']='subido por';
$string['uploaded_on']='Subido en';
$string['new_users_count']='Recuento de nuevos usuarios';
$string['sup_warningscount']='Recuento de advertencias del supervisor';
$string['warningscount']='Recuento de advertencias';
$string['errorscount']='Recuento de errores';
$string['updated_userscount']='Recuento de usuarios actualizado';
$string['personalinfo']='Información personal :';
$string['professionalinfo']='Informacion profesional :';
$string['otherinfo']='Otra información :';
$string['delete']='Eliminar';
$string['pictureof']='Imagen de';
$string['syncnow'] = 'Sincronizar ahora';
$string['authmethod'] = 'Método de autenticación';