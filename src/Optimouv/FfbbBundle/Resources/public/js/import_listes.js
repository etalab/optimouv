// Fonction to controller si l'element exists ou pas
jQuery.fn.exists = function(){return this.length>0;}


function addEventHandlerImportListeParticipants(){
  // bouton pour importer une liste de participants
  $("#btn_import_liste_participants").click(function(){
    console.log("upload listeParticipants");

    var data = new FormData();
    $.each($('#input_liste_participants')[0].files, function(i, file) {
      data.append('file-'+i, file);
    });

    $.ajax({
      url: 'creer-liste-participants',
      type: 'POST',
      data: data,
      contentType: false,
      dataType : "json",
      processData: false,
      success: function(data, textStatus, jqXHR)
      {
        if(data.success)
        {
          // Success so call function to process the form
          console.log('SUCCESS: ' + data.msg);

          // supprimer les enfants de l'élement select liste_partcipants
          $("#liste_partcipants").empty();

          // rafraiher la liste de participants
          $.each(data.data, function(index, value){
            $("#liste_partcipants").append("<option value=" + value.id + ">" + value.nom + "</option>");
          });

          // mettre à jour le statut d'upload
          $("#msg_upload_liste_participants").text(data.msg);

          // afficher le msg d'upload
          $("#collapse_statut_upload_participants").removeClass("collapse");

        }
        else
        {
          // Handle errors here
          console.log('ERRORS: ' + data.msg);

          erreurMsg = data.msg;
          console.log('erreurMsg : ' + erreurMsg );

          // mettre à jour le statut d'upload
          var erreurMsgSplit = erreurMsg.split('!');

          // nettoyer ancien texte
          $("#msg_upload_liste_participants").empty();

          // mettre à jour le contenu du message d'erreur
          for (i = 0 ; i < erreurMsgSplit.length ; i ++ ){
            var iterMsg = erreurMsgSplit[i] + '<br>';
            $("#msg_upload_liste_participants").append(iterMsg);
          }

          // afficher le msg d'upload
          $("#collapse_statut_upload_participants").removeClass("collapse");
        }
      },
      error: function(jqXHR, textStatus, errorThrown)
      {
        // Handle errors here
        console.log('ERRORS: ' + textStatus);
      }
    });

  });

}

function addEventHandlerImportListeLieux(){
  // bouton pour importer une liste de participants
  $("#btn_import_liste_lieux").click(function(){
    console.log("upload listeLieux");

    var data = new FormData();
    $.each($('#input_liste_lieux')[0].files, function(i, file) {
      data.append('file-'+i, file);
    });

    $.ajax({
      url: 'creer-liste-lieux',
      type: 'POST',
      data: data,
      contentType: false,
      processData: false,
      dataType : "json",
      success: function(data, textStatus, jqXHR)
      {
        console.log(data);

        if(data.success)
        {
          // Success so call function to process the form
          console.log('SUCCESS: ' + data.msg);


          // supprimer les enfants de l'élement select liste_partcipants
          $("#liste_lieux").empty();

          $.each(data.data, function(index, value){
            $("#liste_lieux").append("<option value=" + value.id + ">" + value.nom + "</option>");
          });

          // mettre à jour le statut d'upload
          $("#msg_upload_liste_lieux").text(data.msg);

          // afficher le msg d'upload
          $("#collapse_statut_upload_lieux").removeClass("collapse");

        }
        else
        {
          // Handle errors here
          console.log('ERRORS: ' + data.msg);

          erreurMsg = data.msg;
          console.log('erreurMsg : ' + erreurMsg );

          // mettre à jour le statut d'upload
          var erreurMsgSplit = erreurMsg.split('!');

          // nettoyer ancien texte
          $("#msg_upload_liste_lieux").empty();

          // mettre à jour le contenu du message d'erreur
          for (i = 0 ; i < erreurMsgSplit.length ; i ++ ){
            var iterMsg = erreurMsgSplit[i] + '<br>';
            $("#msg_upload_liste_lieux").append(iterMsg);
          }

          // afficher le msg d'upload
          $("#collapse_statut_upload_lieux").removeClass("collapse");



        }
      },
      error: function(jqXHR, textStatus, errorThrown)
      {
        // Handle errors here
        console.log('ERRORS: ' + textStatus);
      }
    });

  });

}

function addEventHandlerSelectListeParticipants(){
  $("#liste_partcipants").change(function(){

    // obtenir l'id de la liste participants choisie
    var idListeParticipants = $(this).val();
    console.log("select liste participants: " + idListeParticipants);

    if ($("#btn_select_liste_participants").exists() == false) {
      // ajoter un bouton select s'il n'existe pas
      var newButtonListeParticipants = $('<button id="btn_select_liste_participants" type="submit" class="btn btn-default  pull-right">Utiliser cette liste </button>');
      $("#div_participants").append(newButtonListeParticipants);

    }
    // ajouter un event handler
    addEventHandlerUseListeParticipants(idListeParticipants);

  });

}

function addEventHandlerSelectListeLieux(){
  $("#liste_lieux").change(function(){

    // obtenir l'id de la liste lieux choisie
    var idListeLieux = $(this).val();
    console.log("select liste lieux: " + idListeLieux);

    if ($("#btn_select_liste_lieux").exists() == false) {
      // ajoter un bouton select s'il n'existe pas
      var newButtonListeLieux = $('<button id="btn_select_liste_lieux" type="submit" class="btn btn-default  pull-right">Utiliser cette liste </button>');
      $("#div_lieux").append(newButtonListeLieux);

    }
    // ajouter un event handler
    addEventHandlerUseListeLieux(idListeLieux);

  });
}

function addEventHandlerUseListeParticipants(idListeParticipants){
  $("#btn_select_liste_participants").click(function(){
    console.log("addEventHandlerUseListeParticipants: " + idListeParticipants);
    //$.redirect('select_liste_participants/'+idListeParticipants, {});
    $.redirect('select-liste-participants', {"idListeParticipants" : idListeParticipants});

  });
}

function addEventHandlerUseListeLieux(idListeLieux){
  $("#btn_select_liste_lieux").click(function(){
    console.log("addEventHandlerUseListeLieux: " + idListeLieux);
    //$.redirect('select_liste_lieux/'+idListeLieux, {});
    $.redirect('/', {"idListeLieux" : idListeLieux});

  });

}



$(document).ready(function () {

  addEventHandlerImportListeParticipants();
  addEventHandlerImportListeLieux();
  addEventHandlerSelectListeParticipants();
  //addEventHandlerSelectListeLieux();

});

