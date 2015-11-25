function addEventHandlerImportListePartcipants(){
  // bouton pour importer une liste de participants
  $("#btn_liste_participants").click(function(){
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
            $("#liste_partcipants").append("<option value=" + value.nom + ">" + value.nom + "</option>");
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

  // bouton pour importer une liste de lieux
  $("#btn_liste_lieux").click(function(){
    console.log("upload listeLieux");
    console.log($("#input_liste_lieux").val());

  });


}
$(document).ready(function () {

  addEventHandlerImportListePartcipants();

});

