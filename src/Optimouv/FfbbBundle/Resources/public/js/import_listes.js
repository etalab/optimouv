// Fonction to controller si l'element exists ou pas
jQuery.fn.exists = function(){return this.length>0;}


function addEventHandlerImportListePartcipants(){
  // bouton pour importer une liste de participants
  $("#btn_import_liste_participants").click(function(){
    console.log("upload listeParticipants");

    var data = new FormData();
    $.each($('#input_liste_participants')[0].files, function(i, file) {
      data.append('file-'+i, file);
    });

    $.ajax({
      url: 'creer_liste_participants',
      type: 'POST',
      data: data,
      contentType: false,
      processData: false,
      success: function(data, textStatus, jqXHR)
      {
        if(typeof data.error === 'undefined')
        {
          // Success so call function to process the form
          console.log('SUCCESS: ' + data);

          // supprimer les enfants de l'élement select liste_partcipants
          $("#liste_partcipants").empty();

          $.each(data, function(index, value){
            $("#liste_partcipants").append("<option value=" + value.id + ">" + value.nom + "</option>");
          });

        }
        else
        {
          // Handle errors here
          console.log('ERRORS: ' + data.error);
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
      url: 'creer_liste_lieux',
      type: 'POST',
      data: data,
      contentType: false,
      processData: false,
      success: function(data, textStatus, jqXHR)
      {
        if(typeof data.error === 'undefined')
        {
          // Success so call function to process the form
          console.log('SUCCESS: ' + data);

          // supprimer les enfants de l'élement select liste_partcipants
          $("#liste_lieux").empty();

          $.each(data, function(index, value){
            $("#liste_lieux").append("<option value=" + value.id + ">" + value.nom + "</option>");
          });

        }
        else
        {
          // Handle errors here
          console.log('ERRORS: ' + data.error);
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
    //window.location.href = "select_liste_participants";
    $.redirect('select_liste_participants/'+idListeParticipants, {});





  });
}

function addEventHandlerUseListeLieux(idListeLieux){
  $("#btn_select_liste_lieux").click(function(){
    console.log("addEventHandlerUseListeLieux: " + idListeLieux);
    //window.location.href = "select_liste_lieux";
    $.redirect('select_liste_lieux/'+idListeLieux, {});

  });

}

$(document).ready(function () {

  addEventHandlerImportListePartcipants();
  addEventHandlerImportListeLieux();
  addEventHandlerSelectListeParticipants();
  addEventHandlerSelectListeLieux();
});

