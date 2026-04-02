<?php
// --------------------------------------------------------- 
// block_request is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// block_request is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// COURSE REQUEST MANAGER BLOCK FOR MOODLE
// by Kyle Goslin & Daniel McSweeney
// Copyright 2012-2014 - Institute of Technology Blanchardstown.
// --------------------------------------------------------- 
/**
 * COURSE REQUEST MANAGER
  *
 * @package    local_request
 * @copyright  2018 Hemalatha c arun
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname']='Solicitud';
$string['plugindesc']='Solicitud de aprobaciones';
$string['back']='atrás';
$string['SaveChanges']='Guardar cambios';
$string['ChangesSaved']='Cambios guardados';
$string['SaveAll']='Salvar a todos';
$string['SaveEMail']='Agregar correo electrónico';
$string['Continue']='Seguir';
$string['Enabled']='Habilitado';
$string['Disabled']='Discapacitado';
$string['clickhere']='haga clic aquí';
$string['update']='Actualizar';
// $string['Request']='Solicitud';
$string['administratorConfig']='Otros ajustes';
$string['emailConfig']='Configuración de correo electrónico';
$string['emailConfigContents']='Configurar correos electrónicos de comunicación';
$string['requestStats']='solicitar estadísticas';
$string['emailConfigInfo']='Esta sección contiene las direcciones de correo electrónico de los administradores a quienes se les notificará cada vez que se registre alguna solicitud de curso.';
$string['emailConfigSectionHeader']='Configurar correo electrónico';
$string['emailConfigSectionContents']='Configurar el contenido del correo electrónico';
$string['statsConfigInfo']='Esta sección contiene estadísticas sobre el número actual de solicitudes que se han realizado desde que se utilizó el módulo Administrador de solicitudes de cursos en este servidor.';
$string['totalRequests']='Número total de solicitudes';
$string['config_addemail']='Dirección de correo electrónico';
$string['namingConvetion']='Convención de nomenclatura de cursos';
$string['namingConvetionInstruction']='Course Request Manager configurará sus cursos utilizando una convención de nomenclatura seleccionada.';
$string['namingConvetion_option1']='Solo nombre completo';
$string['namingConvetion_option2']='Nombre corto - Nombre completo';
$string['namingConvetion_option3']='Nombre completo (nombre corto)';
$string['namingConvetion_option4']='Nombre corto - Nombre completo (año)';
$string['namingConvetion_option5']='Nombre completo (año)';
$string['request']='CRManager';
$string['requestDisplay']='Administrador de solicitudes de cursos';
$string['requestDisplaySearchForm']='Configurar formulario de solicitud - Página 1';
$string['requestWelcome']='Bienvenido a Moodle Course Request Manager. Antes de solicitar un nuevo curso, consulte las directrices locales.';
$string['requestRequestBtn']='Solicitar una nueva configuración de curso';
$string['requestExstingTab']='Solicitudes existentes';
$string['requestHistoryTab']='Solicitar historial';
$string['requestActions']='Comportamiento';
$string['requestConfirmCancel']='¿Está seguro de que desea cancelar esta solicitud?';
$string['requestnonePending']='¡Lo siento, no hay nada pendiente!';
$string['requestEnrolmentInstruction']='El Administrador de solicitudes de cursos puede generar una clave de inscripción automática o puede optar por solicitar al usuario una clave de inscripción de su elección.';
$string['requestEnrolmentOption1']='Clave generada automáticamente';
$string['requestEnrolmentOption2']='Solicitar al usuario la clave';
$string['requestEnrolmentOption3']='No pidas llave';
$string['deleteAllRequests']='Eliminar todas las solicitudes actuales y archivadas';
$string['deleteOnlyArch']='Eliminar solo solicitudes archivadas';
$string['clearHistoryTitle']='Historia clara';
$string['allowSelfCategorization']='Permitir al usuario seleccionar una categoría';
$string['allowSelfCategorization_desc']='Cuando está habilitado, se le pedirá al usuario que seleccione una ubicación en el catálogo de Moodle para colocar su curso.';
$string['selfCatOn']='Autocategorización activada';
$string['selfCatOff']='Auto categorización desactivada';
$string['sureDeleteAll']='¿Estás seguro de que deseas eliminar TODO el historial?';
$string['sureOnlyArch']='¿Está seguro de que desea eliminar solo los registros archivados?';
$string['yesDeleteRecords']='Si eliminar';
$string['recordsHaveBeenDeleted']='Se han eliminado los registros';
$string['clickHereToReturn']='Haga clic aquí para regresar';
$string['selectedcategory']='Categoría';
$string['requestReview_Summary']='Resumen de solicitud';
$string['requestReview_intro1']='Revise la siguiente información detenidamente antes de enviar su solicitud.';
$string['requestReview_intro2']='Su solicitud será atendida lo antes posible.';
$string['requestReview_status']='ESTADO';
$string['requestReview_requestType']='tipo de solicitud';
$string['requestReview_moduleCode']='Código del curso';
$string['requestReview_moduleName']='Nombre del curso';
$string['requestReview_originator']='Autor';
$string['requestReview_SubmitRequest']='Enviar peticion';
$string['requestReview_AlterRequest']='Modificar solicitud';
$string['requestReview_CancelRequest']='Cancelar petición';
$string['requestReview_creationDate']='Fecha de creación';
$string['requestReview_requestType']='tipo de solicitud';
$string['requestReview_OpenDetails']='Abrir detalles';
$string['requestReview_ApproveRequest']='Aprobar solicitud';
$string['requestReview_ApproveRequest']='Aprobar solicitud';
$string['requestReview_courseName']='Nombre del curso';
$string['requestReview_courseCode']='Código del curso';
$string['comments_date']='Fecha y hora';
$string['comments_message']='Mensaje';
$string['comments_from']='Desde';
$string['comments_Header']='Agregar / ver comentarios';
$string['comments_Forward']='Todos los comentarios se reenviarán automáticamente por correo electrónico también.';
$string['comments_PostComment']='publicar comentario';
$string['denyrequest_Title']='Instalación de solicitud de curso - Solicitud denegada';
$string['denyrequest_Instructions']='Describe a continuación por qué se ha rechazado la solicitud';
$string['denyrequest_Btn']='Denegar solicitud';
$string['denyrequest_reason']='Describe a continuación por qué se ha rechazado la solicitud (máximo 280 caracteres)';
$string['approverequest_Title']='Instalación de solicitud de curso: aprobación de solicitud';
$string['approverequest_New']='Se ha creado un nuevo curso';
$string['approverequest_Process']='Ha comenzado el proceso de entrega';
$string['noPending']='¡Lo siento, no hay nada pendiente!';
$string['Status']='Estado';
$string['status']='ESTADO';
$string['creationdate']='Fecha de creación';
$string['requesttype']='tipo de solicitud';
$string['originator']='Autor';
$string['comments']='Comentarios';
$string['bulkactions']='Acciones masivas';
$string['withselectedrequests']='con solicitudes seleccionadas';
$string['existingrequests']='Solicitudes existentes';
$string['actions']='Comportamiento';
$string['currentrequests']='Solicitudes actuales';
$string['archivedrequests']='Solicitudes archivadas';
$string['myarchivedrequests']='Mis solicitudes archivadas';
$string['allarchivedrequests']='Todas las solicitudes archivadas';
$string['configure']='Configurar el administrador de solicitudes de cursos';
$string['requestline1']='Consulte las pautas internas para nombrar cursos.';
$string['requestadmin']='Administración de solicitudes de cursos';
$string['configureHeader']='Facilidad de solicitud de curso: configuración de solicitud';
$string['approve']='Aprobar';
$string['deny']='Negar';
$string['edit']='Editar';
$string['cancel']='Cancelar';
$string['delete']='Eliminar';
$string['view']='Ver';
$string['viewmore']='Ver más';
$string['addviewcomments']='Agregar / ver comentarios';
$string['configurecoursemanagersettings']=' Configurar los ajustes del administrador de solicitudes de cursos';
$string['configurecourseformfields']='  Configurar formulario de solicitud - Página 1';
$string['informationform']=' Configurar formulario de solicitud - Página 2';
$string['modrequestfacility']='Facilidad de solicitud de curso';
$string['step1text']='Paso 1: Detalles de la solicitud del curso';
$string['modexists']='Parece que el curso que solicita ya existe en el servidor.';
$string['modcode']='Código del curso';
$string['modname']='Nombre del curso';
$string['catlocation']='Ubicación del catálogo';
$string['lecturingstaff']='Personal de conferencias';
$string['actions']='Comportamiento';
$string['noneofthese']='¿Ninguno de esos? Continuar haciendo un nuevo curso';
$string['sendrequestforcontrol']='Enviar solicitud de control';
$string['sendrequestemail']='Enviar correo electrónico de solicitud';
$string['emailswillbesent']='Los correos electrónicos se enviarán al propietario del curso. Una vez que envíe una solicitud, espere una respuesta.';
$string['viewsummary']='Ver resumen';
$string['addviewcomments']='Agregar / ver comentarios';
$string['approvecourse']='Aprobar curso';
$string['denycourse']='Denegar solicitud de curso';
$string['bulkdeny']='Denegar a granel';
$string['bulkapprove']='Aprobar a granel';
$string['approvingcourses']='Aprobar cursos ...';
$string['managersettings']='Configuración del administrador';
$string['formpage1']='Formulario Página 1';
$string['formpage2']='Formulario Página 2';
$string['formpage2builder']='Formulario Página 2 Builder';
$string['previewform']='Formulario de vista previa';
$string['courseexists']='El curso existe';
$string['requestcontrol']='Solicitar control';
$string['historynav']='Historia';
$string['searchAuthor']='Autor';
$string['search_side_text']='Buscar';
$string['searchbuttontext']='¡Buscar!';
$string['quickapprove']='Aprobación rápida';
$string['quickapprove_desc']='¿Aprobar rápidamente este curso?';
$string['configureemailsettings']='Configurar los ajustes de correo electrónico';
$string['configureemailsettings_desc']='Esta sección le permite configurar los ajustes de correo electrónico para esta herramienta';
$string['configureadminsettings']='Configuración de administrador';
$string['configureadminsettings_desc']='Adición de configuraciones adicionales para Course Request Manager';
$string['required_field']='Campo requerido';
$string['optional_field']='Campo opcional';
$string['request:myaddinstance']='Agregar instancia';
$string['request:addinstance']='Agregar instancia';
$string['displayListWarningTitle']='ADVERTENCIA';
$string['displayListWarningSideText']='Este nombre corto ya existe en la base de datos de moodle. Se requiere la atención del administrador. Esta solicitud está excluida de las acciones masivas.';
$string['nocatselected']='Lo sentimos, no se ha seleccionado ninguna categoría para este curso.';
$string['customdeny']='Plantillas de texto de negación';
$string['customdenydesc']='Los administradores pueden rechazar las solicitudes de cursos por varias razones. Describir el motivo de una denegación en un correo electrónico puede llevar mucho tiempo. Esta función le permite crear hasta cinco razones que pueden seleccionarse rápidamente durante el proceso de denegación. Max 250 caracteres';
$string['customdenyfiller']='Puede ingresar un motivo de denegación aquí (máximo 250 caracteres)';
$string['denytext1']='Razón 1';
$string['denytext2']='Razón 2';
$string['denytext3']='Razón 3';
$string['denytext4']='Razón 4';
$string['denytext5']='Razón 5';
$string['cannotrequestcourse']='Lo sentimos, su cuenta no tiene privilegios suficientes para solicitar un curso. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['cannotviewrecords']='Lo sentimos, su cuenta no tiene privilegios suficientes para ver registros. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['cannotapproverecord']='Lo sentimos, su cuenta no tiene privilegios suficientes para aprobar registros. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['cannoteditrequest']='Lo sentimos, su cuenta no tiene privilegios suficientes para editar un registro. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['cannotcomment']='Lo sentimos, tu cuenta no tiene privilegios suficientes para comentar. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['cannotdelete']='Lo sentimos, su cuenta no tiene privilegios suficientes para eliminar un registro. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$stirng['cannotdenyrecord']='Lo sentimos, su cuenta no tiene privilegios suficientes para negar un registro. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['cannotviewconfig']='Lo sentimos, su cuenta no tiene privilegios suficientes para ver la configuración. Debe estar asignado a un rol del sistema con suficientes privilegios.';
$string['request:addcomment']='Agregar comentario';
$string['request:addrecord']='Agregar registro';
$string['request:approverecord']='Aprobar registro';
$string['request:deleterecord']='Eliminar el registro';
$string['request:denyrecord']='Denegar registro';
$string['request:editrecord']='Editar registro';
$string['request:viewrecord']='Ver registro';
$string['request:viewconfig']='Ver configuración';
$string['requestcourse']='Requisar';
$string['request_confirm_message']='<div class="pl-15 pr-15">¿Estás seguro de que quieres solicitar {$a->component} ¿inscripción?';
$string['viewrequest']='Lista de solicitudes';
$string['requestedby']='Solicitado por';
$string['compname']='Componente';
$string['sorting']='Clasificación';
$string['requesteddate']='Fecha solicitada';
$string['confirmmsgfor_approve']='<div class="pl-15 pr-15">¿Seguro que quieres inscribirte? {$a->requesteduser} a {$a->component} ';
$string['confirmmsgfor_deny']='<div class="pl-15 pr-15">¿Estás seguro de querer rechazar? {$a->requesteduser} solicitud';
$string['success_add']='<div class="pl-15 pr-15">La solicitud ha sido enviada, espere la aprobación y recibirá una notificación en breve.';
$string['success_approve']='Inscrito exitosamente';
$string['success_deny']='Se rechazó, comuníquese con una autoridad superior para obtener más información.';
$string['confirmmsgfor_delete']='<div class="pl-15 pr-15">¿Seguro que quieres eliminar? {$a->requesteduser} solicitud';
$string['success_delete']='<div class="pl-15 pr-15">La solicitud se eliminó correctamente';
$string['confirmmsgfor_add']='<div class="pl-15 pr-15">¿Estás seguro de que quieres solicitar esto?<b>{$a->componentname}</b>\' {$a->component}?';
$string['alreadyrequested']='<div class="pl-15 pr-15">Ya ha solicitado lo mismo, pronto se le notificará sobre la aprobación.';
$string['responder']='Respondedor';
$string['respondeddate']='Fecha de respuesta';
$string['componentname']='Nombre del componente';
$string['no_requests']='Aún no se agregaron solicitudes';
$string['firstrequestedfirst']='Primero solicitado primero';
$string['APPROVED']='APROBADO';
$string['REJECTED']='RECHAZADO';
$string['PENDING']='PENDIENTE';
$string['latestfirst']='Último primero';
$string['classroom']='Aula';
$string['elearning']='Aprendizaje electrónico';
$string['learningplan']='Ruta de aprendizaje';
$string['program']='Programa';
$string['certification']='Certificación';
$string['left_menu_requests']='Administrar solicitudes';
$string['course']='Curso';
$string['eventrequestcreated']='Solicitud local creada';
$string['eventrequestapproved']='Solicitud local aprobada';
$string['eventrequestdeleted']='Solicitud local eliminada';
$string['eventrequestrejected']='Solicitud local rechazada';
$string['information']='Información';
$string['capacity_check'] = "<div class='alert alert-danger'> Todos los asientos están ocupados.</div>";
$string['messageprovider:request_add']='Añadir solicitud';
$string['messageprovider:request_approve']='Aprobar solicitud';
$string['messageprovider:request_deny']='Denegar solicitud';
$string['modidnotset']='Nuevo ID de mod no establecido';
$string['approved']='Aprobado';
$string['rejected']='Rechazado';
$string['filters']='Filtros';
$string['reject']='Rechazar';
$string['confirm']='Confirmar';
$string['savecontinue']='Guardar Continuar';
$string['assign']='Asignar';
$string['save']='Salvar';
$string['previous']='Anterior';
$string['skip']='Omitir';
$string['cancel']='Cancelar';
$string['has_requested_for_enrolling_to'] = 'ha solicitado inscribirse en';
