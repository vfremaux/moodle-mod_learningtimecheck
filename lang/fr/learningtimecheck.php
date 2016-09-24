<?php
// This file is part of the learningtimecheck plugin for Moodle - http://moodle.org/
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

$string['learningtimecheck:addinstance'] = 'Peut ajouter une instance';
$string['learningtimecheck:edit'] = 'Créer et modifier une liste';
$string['learningtimecheck:emailoncomplete'] = 'Recevoir les notifications de completion';
$string['learningtimecheck:preview'] = 'Prévisualiser une liste';
$string['learningtimecheck:updatelocked'] = 'Mettre à jour les marques verrouilées';
$string['learningtimecheck:updateother'] = 'Modifier les marques des étudiants';
$string['learningtimecheck:updateown'] = 'Modifier vos propres marques';
$string['learningtimecheck:viewreports'] = 'Voir la progression';
$string['learningtimecheck:viewcoursecalibrationreport'] = 'Voir le rapport de calibrage du cours';
$string['learningtimecheck:viewmenteereports'] = 'Voir mes progrés (élève)';
$string['learningtimecheck:viewreports'] = 'Voir la progression des élèves';
$string['learningtimecheck:viewtutorboard'] = 'Voir le tableau de synthèse des temps tutoraux';
$string['learningtimecheck:forceintrainingsessions'] = 'Peut forcer les temps forfaitaires dans les rapports de session de formmation.';

$string['addcomments'] = 'Ajouter des commentaires';
$string['additem'] = 'Ajouter';
$string['additemalt'] = 'Ajouter un nouvel élément à la liste';
$string['additemhere'] = 'Ajouter un élément après celui-ci';
$string['addownitems'] = 'Ajoutez vos éléments';
$string['addownitems-stop'] = 'Ne plus ajouter ses propres éléments';
$string['allowmodulelinks'] = 'Permettre les liaisons aux modules d\'activité';
$string['allreports'] = 'Rapports';
$string['anygrade'] = 'N\'importe quelle valeur';
$string['applytoall'] = 'Appliquer à tout';
$string['autopopulate'] = 'Capturer les modules de cours dans la liste';
$string['autoupdate'] = 'Auto-marquer quand l\'activité est complète';
$string['autoupdate_task'] = 'Mise à jour automatique des marques';
$string['task_compile'] = 'Compilation des rapports';
$string['autoupdatenote'] = 'Seules les marques "étudiantes" sont mies à jour automatiquement - Il n\'y aura aucun marquage automatique pour les marques enseignant.';
$string['autoupdatewarning_both'] = 'Certains éléments de cette liste sont mis à jour automatiquement (lorsque les étudiants complètent les activités liées). Cependant, comme cette liste autorise les \'marques et contremarques\' la barre de progression ne sera pas mise à jour tant que les enseignants ne valident pas les marques déclarées.';
$string['autoupdatewarning_student'] = 'Certains éléments de cette liste sont mis à jour automatiquement (lorsque les étudiants complètent les activités liées).';
$string['autoupdatewarning_teacher'] = 'La mise à jour automatique des marques a été activée pour cette liste, mais elles ne seront pas affichées car seules les contremarques de l\'enseignant sont affichables.';
$string['average'] = '(Moy)';
$string['badlearningtimecheckid'] = 'ID de liste incorrect';
$string['backtocourse'] = 'Revenir au cours';
$string['backtosite'] = 'Revenir à la page d\'accueil';
$string['both'] = 'Les deux';
$string['calendardescription'] = 'Cet événement a été ajouté à la liste : $a';
$string['canceledititem'] = 'Annuler';
$string['changetextcolour'] = 'Couleur de texte suivante';
$string['checkeditemsdeleted'] = 'Les éléments sélectionnés ont été supprimés';
$string['checks'] = 'Marques';
$string['collapseheaders'] = 'Réduire les titres';
$string['comments'] = 'Commentaires';
$string['configlastcompiled'] = 'Dernière date de compilation par le cron';
$string['configlastcompiled_desc'] = 'Changer cette date recompilera les événements non pris en compte.';
$string['completionboard'] = 'Tableau d\'accomplissements';
$string['completiongradehelp'] = 'Les notes d\'accomplissement des objectifs sont des scores entiers, pas des pourcentages';
$string['completionpercent'] = 'Pourcentage d\'éléments marqués attendu';
$string['completionpercentgroup'] = 'Nécessite un marquage';
$string['configallowmodulelinks'] = 'Permettre aux éléments de marquage d\'être liés à des activités de Moodle (peut ralentir l\'affichage des listes)';
$string['configusestatscoupling'] = 'Couplage avec les Rapports de session';
$string['configallowoverrideusestats'] = 'permettre la surcharge des Rapports de session';
$string['configallowoverrideusestats_desc'] = 'Si activé, il est possible de déclarer certains items pour que leur crédit temps remplace la valeur "mesurée" dans les rapports de session de formation.';
$string['configautoupdateusecron'] = 'Utiliser les tâches programmées pour la mise à jour automatique';
$string['configautoupdateusecron_desc'] = 'Si activé, la mise à jour automatique des marques est réalisée par les taches planifiées de Moodle, avec un certain retard. Si vous pouvez supporter ce délai, 
cela évite que les marques soient calculées pendant que vous naviguez dans les interfaces de la liste de temps pédagogique, ce qui peut rallentir celles-ci notablement.';
$string['configapplyfiltering'] = 'Appliquer le filtrage';
$string['configapplyfiltering_desc'] = 'Si activé, le fitlrage de jours et heures ouvrées des rapports de temps d\'apprentissage s\'appliquent aux marques générées par la liste. Sinon, le module de temps d\'apprentissage n\'est pas affecté par le filtrage (Sans ipact sur les rapports de session qui continuent à subir le fltrage.).';
$string['configcsvformat'] = 'Format CSV';
$string['configcsvencoding'] = 'Encodage';
$string['configcsvencoding_desc'] = 'Encodage du fichier CSV';
$string['configcsvfieldseparator'] = 'Séparateur de champ';
$string['configcsvfieldseparator_desc'] = 'Séparateur de champ CSV';
$string['configcsvlineseparator'] = 'Fin de ligne';
$string['configcsvlineseparator_desc'] = 'Fin de ligne CSV';
$string['configinitialautocapture'] = 'Autocapture initiale';
$string['configinitialautocapture_desc'] = 'Quel mode de découverte autoamatique des items est activé lors de la création d\'une nouvelle instance de liste';
$string['configinitiallymandatory'] = 'Initialement obligatoire';
$string['configinitiallymandatory_desc'] = 'Si marqué, tous les éléments découverts dans les cours seront marqués comme obligatoires. Sinon, tous les éléments créés automatiquement seront initalement marqués comme facultatifs';
$string['configinitialcredittimeon'] = 'Temps forfaitaires activés initiallement';
$string['configinitialcredittimeon_desc'] = 'Si marqué, les temps forfaitaires sont disponibles pour toute nouvelle checklist créée';
$string['configintegrateusestats'] = 'Intégrer les mesures réelles d\'activité';
$string['configintegrateusestats_desc'] = 'Si activé les d\'activité "au réel" seront intégrées aux rapports';
$string['configlearningtimecheckautoupdate'] = 'Bénéficier de cette fonctionnalité demande la quelques modifications locales du noyau de Moodle, lisez le document mod/learningtimecheck/README.txt pour plus de détails ou consultez un intégrateur.';
$string['configlearningtimecheckautoupdateusecron'] = 'Les accomplissements seront automatiquement marqués sur la base d\'événements des traces';
$string['configmy'] = 'Affichage dans les vues d\'ensemble de cours';
$string['configshowcompletemymoodle'] = 'Montrer les listes d\'avancement complétées sur les pages personnalisées';
$string['configshowcompletemymoodle_desc'] = 'Si cette option est activée, alors les listes complétée ne seront pas visible dans les pages personnalisées';
$string['configshowmymoodle'] = 'Montrer les listes d\'avancement sur les pages personnalisées';
$string['configshowmymoodle_desc'] = 'Si cette option est activée, alors les listes de progression et leurs barres ne seront pas visible dans les pages personnalisées';
$string['configcouplecredittomandatoryoption'] = 'Couplage Credit/Obligatoire';
$string['configcouplecredittomandatoryoption_desc'] = 'Si actif, un item de liste deviendra obligatoire lorsqu\on lui affecte un crédit temps pédagogique non nul';
$string['confirmdeleteitem'] = 'Etes-vous certain de vouloir définitivement supprimer ces éléments ?';
$string['coursecalibrationreport'] = 'Rapport de calibrage du cours';
$string['coursecompletionboard'] = 'Tableau d\'accomplissements du cours';
$string['coursetotaltime'] = 'Temps cours';
$string['credit'] = 'Crédit';
$string['credittime'] = 'Temps forfaitaire apprenant ';
$string['itemcredittime'] = 'Crédit temps : {$a} min.';
$string['deleteitem'] = 'Supprimer cet élément';
$string['disabled'] = 'Désactivé';
$string['itemsdone'] = 'Activités réalisées';
$string['edit'] = 'Modifier la liste';
$string['editchecks'] = 'Modifier les marques';
$string['editdatesstart'] = 'Activer les dates';
$string['editdatesstop'] = 'Désactiver les dates';
$string['editingoptions'] = 'Actions globales d\'édition';
$string['edititem'] = 'Modifier cet élément';
$string['eithercheck'] = 'L\'un ou l\'autre';
$string['emailoncomplete'] = 'Notifier les enseignants quand la liste est complète';
$string['emailoncompletebody'] = 'L\'utilisateur {$a->user} a terminé le marquage de la liste \'$a->learningtimecheck\' Consulter la liste ici :';
$string['emailoncompletesubject'] = 'L\'apprenant {$a->user} a complété sa liste \'{$a->learningtimecheck}\'';
$string['enablecredit'] = ' Imposer le temps forfaitaire (*) ';
$string['enablecredit_desc'] = ' (*) si activé, le crédit temps est imposé dans les rapports de session de formation (add-on) au lieu du temps constaté.';
$string['estimated'] = 'Déclaré (auto-estimé)';
$string['errornosuchuser'] = 'Cet utilisateur n\'existe peut-être plus.';
$string['errorbadinstance'] = 'Cette instance de liste est manquante : cmid {$a} ';
$string['errornoeditcapability'] = 'Vous n\'zavez pas les droits nécessaires pour exporter les items';
$string['expandheaders'] = 'Montrer les titres';
$string['expectedtutored'] = 'Temps tutorat prévu';
$string['export'] = 'Exporter les éléments';
$string['exportexcel'] = 'Exporter en XLS';
$string['exportpdf'] = 'Générer PDF';
$string['forceupdate'] = 'Mettre à jour toutes les marques automatiques';
$string['fullview'] = 'Voir le détail, et si besoin, marquer manuellement';
$string['fullviewdeclare'] = 'Voir le détail, et si besoin, marquer manuellement et déclarer les temps';
$string['gradetocomplete'] = 'Score pour valider :';
$string['guestsno'] = 'Vous n\'avez pas le droit de consulter cette liste';
$string['headingitem'] = 'Cet élément est un titre de rubrique - il n\'a pas de case à cocher associée';
$string['hiddenbymodule'] = 'Cet élément correspond à un module d\'activité non visible.';
$string['import'] = 'Importer les éléments';
$string['importfile'] = 'Choisir un fichier d\'import';
$string['importfromcourse'] = 'Tout le cours';
$string['importfrompage'] = 'Page courante';
$string['importfrompageandsubs'] = 'Page courante et toutes les sous-pages';
$string['importfromsection'] = 'Section courante';
$string['importfromtoppage'] = 'tout le chapitre (à partir de la première page)';
$string['indentitem'] = 'Indenter les éléments';
$string['isdeclarative'] = 'Mode déclaratif';
$string['ismandatory'] = 'Obligatoire';
$string['itemcomplete'] = 'Complété';
$string['items'] = 'Eléments de liste';
$string['itemstodo'] = 'Activités à réaliser';
$string['learningvelocities'] = 'Vélocité d\'apprentissage';
$string['uservelocity'] = 'Vélocité';
$string['days'] = 'Jours';
$string['hours'] = 'Heures';
$string['back'] = 'Retour';
$string['lastcompiledtime'] = 'Dernier log compilé';
$string['learningtimecheck'] = 'Avancement du travail';
$string['learningtimecheck_autoupdate_use_cron'] = 'Mise à jour automatique par cron';
$string['learningtimecheckautoupdate'] = 'Permettre la mise à jour automatique de la liste';
$string['learningtimecheckfor'] = 'Liste pour ';
$string['learningtimecheckintro'] = 'Introduction';
$string['learningtimechecksettings'] = 'Réglages';
$string['linktomodule'] = 'Lier à l\'activité';
$string['listpreview'] = 'Prévisualisation de la liste';
$string['myprogress'] = 'Ma progression';
$string['lockteachermarks'] = 'Verrouiler les marques de l\'enseignant';
$string['lockteachermarks_help'] = 'Lorsque ce réglage est activé, Les marques validées par les enseignants ne peuvent plus être retirées, sauf par les utilisateurs disposant de la capacité \'mod/learningtimecheck:updatelocked\'.';
$string['lockteachermarkswarning'] = 'Note: une fois mémorisées, vous ne pouvez plus modifier des marques positives';
$string['marktypes'] = 'Types de marques';
$string['mandatory'] = 'obligatoires ';
$string['optional'] = 'facultatifs ';
$string['modulename'] = 'Avancement et temps pédagogiques';
$string['modulenameplural'] = 'Avancements et temps pédagogiques';
$string['moduletotaltime'] = 'Temps pour ce module';
$string['moveitemdown'] = 'Vers le bas';
$string['moveitemup'] = 'Vers le haut';
$string['noitems'] = 'Aucun élément dans la liste';
$string['nochecks'] = 'Aucune marque dans ce cours';
$string['nousers'] = 'Aucun utilisateur dans ce contexte';
$string['noinstances'] = 'Aucune instance de Suivi des Temps Pédagogiques';
$string['completionboard'] = 'Tableau des accomplissements';
$string['totalcourseratio'] = 'Couverture sur le cours';
$string['coursetotalitems'] = 'Total Items du cours';
$string['optionalhide'] = 'Cacher les éléments facultatifs';
$string['optionalitem'] = 'Cet élément est facultatif';
$string['optionalshow'] = 'Montrer les éléments facultatifs';
$string['percentcomplete'] = 'Eléments obligatoires';
$string['percentcompleteall'] = 'Tous les éléments';
$string['pluginadministration'] = 'Admministration de la liste d\'avancement';
$string['pluginname'] = 'Avancement et temps pédagogiques';
$string['preview'] = 'Prévisualiser';
$string['progress'] = 'Progression';
$string['progressbar'] = 'Avancement';
$string['ratioleft'] = '% reste à faire (temps)';
$string['realtutored'] = 'Temps tutorat réel';
$string['checksrefreshed'] = 'Les marques ont été recalculées.';
$string['refresh'] = 'Raffraichir les marques (peut être long)';
$string['removeauto'] = 'Enlever les activités du cours';
$string['report'] = 'Voir la progression';
$string['reports'] = 'Rapports globaux';
$string['reportedby'] = 'Commenté par $a ';
$string['reporttablesummary'] = 'Table synthèse des éléments complétés par les étudiants';
$string['requireditem'] = 'Cet élément est obligatoire - vous devez le valider';
$string['resetlearningtimecheckprogress'] = 'Remettre à zéro la progresion et les marques utilisateur';
$string['saveall'] = 'Enregistrer tout';
$string['savechecks'] = 'Enregistrer';
$string['savechecksandeditoff'] = 'Enregistrer et sortir';
$string['showfulldetails'] = 'Voir les détails';
$string['showprogressbars'] = 'Voir les barres de progression';
$string['studenthasdeclared'] = 'L\'étudiant a déclaré : <b>$a</b> minute(s) ';
$string['studentmarkno'] = 'Activité non activée';
$string['studentmarkyes'] = 'Activité activée';
$string['summators'] = 'Sommes et moyennes : ';
$string['teacheralongsidecheck'] = 'Marques étudiantes et contremarques enseignantes';
$string['teachercomment'] = 'Commentaire :';
$string['teachercomments'] = 'Les enseignants peuvent commenter';
$string['teachercredittime'] = 'Temps tutoral';
$string['teachercredittimeforitem'] = 'Temps tutoral pour l\'activité';
$string['teachercredittimeforusers'] = 'Temps tutoral apprenants';
$string['teachercredittimeperuser'] = 'Temps tutoral par étudiant';
$string['teacherdate'] = 'Date de dernière modification par l\'enseignant';
$string['teacheredit'] = 'Mis à jour par';
$string['teacherid'] = 'L\'enseignant qui a porté la dernière modification';
$string['teachermark'] = 'Validation&nbsp;: ';
$string['teachermarkno'] = 'L\'enseignant n\'a pas validé votre marque';
$string['teachermarkundecided'] = 'Les enseignants n\'ont pas encore marqué cet élément';
$string['teachermarkyes'] = 'L\'enseignant à validé votre marque';
$string['teachernoteditcheck'] = 'Marques étudiantes seulement';
$string['teacheroverwritecheck'] = 'Marques enseignantes seulement';
$string['teachertimetodeclare'] = ' Temps tutorat déclaré&nbsp;: ';
$string['teachertimetodeclareonuser'] = 'Temps tuteur commun sur l\'activité';
$string['teachertimetodeclareperuser'] = 'Temps tuteur sur l\'apprenant';
$string['theme'] = 'Theme pour la liste';
$string['timedone'] = 'Temps réalisé';
$string['timeduefromcompletion'] = 'Cette date est forcée par les réglages d\'achévement de l\'activité';
$string['timeleft'] = 'Temps reste à faire';
$string['timesource'] = 'Source';
$string['timetodeclare'] = ' Temps déclaré&nbsp;: ';
$string['toggledates'] = 'Afficher/cacher les dates';
$string['totalcourse'] = 'Total du cours';
$string['totalcoursetime'] = 'Temps pédagogique équivalent total ';
$string['totalestimatedtime'] = 'Dont temps estimés ';
$string['totalized'] = '(Total)';
$string['totalteacherestimatedtime'] = 'Temps tutorat estimé ';
$string['tutorboard'] = 'Rapport de tutorat (réalisé)';
$string['uncheckoptional'] = 'Décocher pour rendre facultatif';
$string['unindentitem'] = 'Désindenter les éléments';
$string['unvalidate'] = 'Refuser';
$string['updatecompletescore'] = 'Enregistrer les modifications';
$string['updateitem'] = 'Mettre à jour';
$string['userdate'] = 'Date de dernière modification par l\'élève';
$string['useritemsallowed'] = 'Les étudiants peuvent ajouter leurs propres éléments';
$string['useritemsdeleted'] = 'Eléments étudiants supprimés';
$string['usetimecounterpart'] = 'Activer les temps forfaitaires standard&nbsp;:';
$string['validate'] = 'Valider';
$string['view'] = 'Voir la liste';
$string['view_pageitem_withoutlinks'] = 'Vue en pavés (étudiants) sans liens';
$string['view_pageitem_progress'] = 'Vue en barre de progression personnelle pour les étudiants';
$string['viewall'] = 'Voir tous les étudiants';
$string['viewallcancel'] = 'Annuler';
$string['viewallsave'] = 'Enregistrer';
$string['viewsinglereport'] = 'Voir la progression de cet étudiant';
$string['viewsingleupdate'] = 'Mettre à jour la progression de cet étudiant';
$string['yesnooverride'] = 'Oui (verrouillé)';
$string['yesoverride'] = 'Oui (non verrouillé)';
$string['save'] = 'Enregistrer';
$string['filtering'] = 'Filtrage sur les utilisateurs';
$string['errornodate'] = 'Erreur : un filtre doit avoir une date';
$string['errornologop'] = 'Erreur : un filtre doit avoir un opérateur logique';
$string['itemenable'] = 'Prendre en compte';
$string['itemdisable'] = 'Ignorer cet élément';

$string['and'] = 'ET';
$string['or'] = 'OU';
$string['xor'] = 'OU ALORS';

$string['courseenroltime'] = 'Date d\'inscription au cours ';
$string['firstcheckaquired'] = 'Date de la première marque obligatoire ';
$string['checkcomplete'] = 'Date de la completion de liste ';
$string['coursestarted'] = 'Date d\'entrée dans le cours (première trace effective) ';
$string['coursecompleted'] = 'Date d\'achévement du cours ';
$string['lastcoursetrack'] = 'Date de la dernière trace du cours ';
$string['onecertificateissued'] = 'Date d\'obtention du premier certificat ';
$string['allcertificatesissued'] = 'Date d\'obtention du dernier certificat '; 
$string['usercreationdate'] = 'Compte utilisateur créé ';
$string['sitefirstevent'] = 'Première trace ';
$string['sitelastevent'] = 'Dernière trace ';
$string['firstcoursestarted'] = 'Premier cours démarré ';
$string['firstcoursecompleted'] = 'Premier cours complété';
$string['usercohortaddition'] = 'Ajout de l\'utilisateur dans la cohorte ';

$string['modulename_help'] = '
Ce module aide les étudiants et les enseignants à marquer et suivre les activités accomplies, tout en définissant et comptabilisant des temps pédagogiques associés
aux activités. Des activités hors ligne peuvent également être comptabilisées, ainsi que des temps déclarés par les étudiants. Les temps validés sont inscrits comme tels
dans les rapports de Session de Formation (rapport non standard) lié au bloc Mesure d\'Activité. 

L\'usage de ce module permet de dissocier la notion d\'accomplissement des objectifs pédagogiques
et règles de poursuite outillées par la notion d\'achèvement du cours, de la notion de compatabilité de temps pédagogique souvent lié à des questions de financement de la formation.
';
$string['autopopulate_help'] = '
<p>En activant cette option, vous ajoutez à la liste tous les modules d\'activité selon la portée choisie.</p>
<p>Vous pouvez choisir d\'intégrer la totalité des modules d\'activité du cours ou seulement ceux de la même section
que là où est implantée la liste.</p>
<p>Une fois synchronisée avec le cours, la liste subira tous les changements qui sont faits dans le cours ou la section, quelles que soient vos actions dans le panneau d\'édition des éléments de liste.</p>
<p>Certaines activités pourront être ignorées dans le contrat pédagogique, en cliquant l\'icone "Oeil" à droite de la définition de l\'élément.</p>
<p>Pour retirer les activités d\'une liste de marquage, vous devez remettre cette option à "Non", puis aller sur le panneau d\'édition des éléments de liste et cliquer sur le bouton "Enlever les activités du cours".</p>
';

$string['autoupdate_help'] = '
<p>Cette fonctionnalité marque automatiquement des activités présentes dans la liste de marquage lorsque certaines actions y sont faites.</p>
<p>"Compléter" une activité peut dépendre du type d\'activité - la voir pour une ressource, soumettre un fichier pour un devoir, poster un premier message dans un forum, ou rejoindre la première fois un chat, etc.</p>
<p>Si vous souhaitez des détails sur l\'événement qui "complète" une activité particulière, demandez à votre administrateur de regarder le fichier "mod/learningtimecheck/autoupdatelib.php"</p>
<p><strong>Note :</strong> La mise à jour des marques automatique dépend du passage de la tâche régulière de mise à jour de Moodle. Il est possible que cette mise à jour puisse prendre plusieurs minutes, selon le réglage de la fréquence de cette tâche. L\'administrateur peut effectuer quelques modifications non standard de Moodle pour rendre cette mise à jour immédiate.</p>
';

$string['emailoncomplete_help'] = '
<p>Quand une liste est compléte (l\'étudiant a coché tous les éléments de la liste ou effectué les activités demandées dans le cas où seul l\'étudiant est concerné, ou l\'enseignant a coché l\'ensemble des éléments, un courriel de notification est envoyé à tous les enseignants du cours.</p>
<p>Un administrateur peut décider qui reçoit ce courriel en utilisant la capacité "mod:learningtimecheck/emailoncomplete" - par défaut, tous les enseignants et non enseignants non éditeur ont cette capacité.</p>
';

$string['emailoncompletesubject'] = 'L\'utilisateur {$a->user} a terminé la liste de travaux \'{$a->learningtimecheck}\'';
$string['emailoncompletesubjectown'] = 'Vous avez terminé la liste de travaux \'{$a->learningtimecheck}\'';
$string['emailoncompletebody'] = 'L\'utilisateur {$a->user} a terminé la liste de travaux \'{$a->learningtimecheck}\' dans le cours \'{$a->coursename}\' 
Voir la liste ici :';

$string['emailoncompletebodyown'] = 'Vous avez terminé les travaux de la liste \'{$a->learningtimecheck}\' du cours \'{$a->coursename}\' 
Voir la liste ici :';
