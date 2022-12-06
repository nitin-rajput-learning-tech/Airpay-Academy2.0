<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    local_groups
 * @copyright  2018 sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addcohort']='Agregar nuevo grupo';
$string['allcohorts']='Todos los grupos';
$string['anycohort']='Alguna';
$string['assign']='Asignar';
$string['assignto']='Grupo \'{$a}\'miembros';
$string['backtocohorts']='Volver a grupos';
$string['bulkadd']='Añadir al grupo';
$string['bulknocohort']='No se encontraron grupos disponibles';
$string['categorynotfound']='Categoría <b>{$a}</b>no encontrado o no tiene permiso para crear un grupo allí. Se utilizará el contexto predeterminado.';
$string['cohort']='Grupo';
$string['cohorts']='Administrar grupos';
$string['pluginname']='Grupos';
$string['cohortsin']='{$a}: grupos disponibles';
$string['assigncohorts']='Asignar miembros del grupo';
$string['component']='Fuente';
$string['contextnotfound']='Contexto <b>{$a}</b>no encontrado o no tiene permiso para crear un grupo allí. Se utilizará el contexto predeterminado.';
$string['csvcontainserrors']='Se encontraron errores en los datos CSV. Vea los detalles abajo.';
$string['csvcontainswarnings']='Se encontraron advertencias en datos CSV. Vea los detalles abajo.';
$string['csvextracolumns']='Columna (s) <b>{$a}</b> será ignorado.';
$string['currentusers']='Usuarios actuales';
$string['currentusersmatching']='Usuarios actuales que coinciden';
$string['defaultcontext']='Contexto predeterminado';
$string['delcohort']='Eliminar grupo';
$string['delconfirm']='¿De verdad quieres eliminar el grupo "{$a}"?';
$string['description']='Descripción';
$string['displayedrows']='{$a->displayed} filas mostradas fuera de {$a->total}.';
$string['duplicateidnumber']='ya existe un grupo con el mismo número de identificación';
$string['editcohort']='Editar grupo';
$string['editcohortidnumber']='Editar ID de grupo';
$string['editcohortname']='Editar el nombre del grupo';
$string['eventcohortcreated']='Grupo creado';
$string['eventcohortdeleted']='Grupo eliminado';
$string['eventcohortmemberadded']='Usuario agregado a un grupo';
$string['eventcohortmemberremoved']='Usuario eliminado de un grupo';
$string['eventcohortupdated']='Grupo actualizado';
$string['external']='Grupo externo';
$string['idnumber']='Identificación del grupo';
$string['memberscount']='Tamaño del grupo';
$string['name']='Nombre';
$string['namecolumnmissing']='Hay un problema con el formato del archivo CSV. Compruebe que incluye los nombres de las columnas.';
$string['namefieldempty']='El nombre del campo no puede estar vacío';
$string['newnamefor']='Nuevo nombre para el grupo {$a}';
$string['newidnumberfor']='Nuevo número de identificación para el grupo {$a}';
$string['nocomponent']='Creado manualmente';
$string['potusers']='Usuarios potenciales';
$string['potusersmatching']='Usuarios coincidentes potenciales';
$string['preview']='Avance';
$string['removeuserwarning']='Eliminar usuarios de un grupo puede dar como resultado la cancelación de la inscripción de usuarios de varios cursos, lo que incluye la eliminación de la configuración del usuario, las calificaciones, la pertenencia al grupo y otra información del usuario de los cursos afectados.';
$string['selectfromcohort']='Seleccionar miembros de la cohorte';
$string['systemcohorts']='Grupos de sistemas';
$string['unknowncohort']='Grupo desconocido ({$a})!';
$string['uploadcohorts']='Subir grupos';
$string['uploadedcohorts']='Subido {$a} grupos';
$string['useradded']='Usuario agregado al grupo "{$a}"';
$string['search']='Buscar';
$string['searchcohort']='Grupo de búsqueda';
$string['uploadcohorts_help']='Los grupos se pueden cargar mediante un archivo de texto. El formato del archivo debe ser el siguiente: * Cada línea del archivo contiene un registro * Cada registro es una serie de datos separados por comas (u otros delimitadores) * El primer registro contiene una lista de nombres de campo que definen el formato del resto del archivo * El nombre de campo obligatorio es el nombre * Los nombres de campo opcionales son idnumber, description, descriptionformat, visible, context, category, category_id, category_idnumber, category_path';
$string['visible']='Visible';
$string['visible_help'] = "Any group can be viewed by users who have 'moodle/cohort:view' capability in the cohort context.</div><br>Visible groups can also be viewed by users in the underlying courses.";
$string['select_all']='Seleccionar todo';
$string['remove_all']='Un Seleccionar ';
$string['not_enrolled_users']='<b>Usuarios no inscritos ({$a})</b>';
$string['enrolled_users']='<b> Asignar usuarios ({$a})</b>';
$string['remove_selected_users']='<b> Anular la asignación de usuarios </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['remove_all_users']='<b> Anular la inscripción de todos los usuarios </b><i class="fa fa-arrow-right" aria-hidden="true"></i><i class="fa fa-arrow-right" aria-hidden="true"></i>';
$string['add_selected_users']='<i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i><b> Asignar usuarios</b>';
$string['add_all_users']=' </div><i class="fa fa-arrow-left" aria-hidden="true"></i><i class="fa fa-arrow-left" aria-hidden="true"></i> </div><b> Inscribir a todos los usuarios </b>';
$string['groups:manage']='Gestionar grupos';
$string['groups:addinstance']='Agregar instancia de grupo local';
$string['groups:create']='Crear grupo local';
$string['groups:delete']='Eliminar grupos locales';
$string['groups:view']='Ver grupos locales';
$string['availablelist']='Usuarios disponibles';
$string['selectedlist']='Usuarios seleccionados';
$string['enrolledlist']='Usuarios inscritos';
$string['completedlist']='Usuarios completados';
$string['enrolluserssuccess']='<b>{$a->changecount}</b> Empleados inscritos con éxito en este <b>"{$a->group}"</b> grupo.';
$string['unenrolluserssuccess']='<b>{$a->changecount}</b> Empleado (s) se anuló con éxito de este <b>"{$a->group}"</b> grupo.';
$string['enrollusers']='Grupo <b>"{$a}"</b> la inscripción está en proceso ...';
$string['un_enrollusers']='Grupo <b>"{$a}"</b> Un registro está en proceso ...';
$string['click_continue']='Haga clic en continuar';
$string['leftmenu_groups']='Administrar grupos';
$string['enroll']='Asignarlos al grupo';
$string['bulk_enroll']='Asignaciones masivas';
$string['user_exist']='{$a} - ya inscrito en este grupo';
$string['im:stats_i']='{$a} Empleados inscritos con éxito en este grupo';
$string['edit']='Comportamiento';
$string['addnewgroups']='Agregar nuevo grupo';
$string['editgroups']='Editar grupo';
$string['editgroup']='Grupo de actualización';
$string['create_group']='Crea un grupo';
$string['groupname']='Falta el nombre del grupo';
$string['assignments']='Asignaciones';
$string['tabgroupid']='Identificación del grupo';
$string['grouptabuserscount']='Número de usuarios';
$string['grouptabmanageusers']='Administrar usuarios';
$string['nogroupstoshow']='No hay grupos para mostrar';
$string['libupdategroup']='Grupo de actualización';
$string['renderepagetype']='página';
$string['rendercenteralignaction']='acción centralign';
$string['admintablegeneraltable']='tabla general administrable';
$string['admintableid']='cohortes';
