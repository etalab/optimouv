#!/usr/bin/python3
import sys
import os.path
import gc
import logging
import datetime
import random
import numpy as np
import math
import db
import json
import requests
import smtplib
from email.mime.text import MIMEText
import time
import pika
from pika.adapters import SelectConnection
# from openpyxl import load_workbook
from lib import *

"""
Function to print exception traceback
useful for debugging purposes
"""
def show_exception_traceback():
	exc_type, exc_value, exc_tb = sys.exc_info()
	fname = os.path.split(exc_tb.tb_frame.f_code.co_filename)[1]
	print("############################################ EXCEPTION OCCURRED ####################################################")
	print("Error Class: %s" %exc_type)
	print("Error Detail: %s " %exc_value)
	print("Filename: %s" %fname)
	print("Line number: %s " %exc_tb.tb_lineno)
	sys.exit()


"""
Import python file from an absolute path
@return: module (Python module)
"""
def absImport(pythonFile):
	try:
		import importlib.machinery
		#split the file we want to import into the path and fileName
		root, fileN = os.path.split(pythonFile)

		# strip the ".py" extension, since we only want the modulename
		fileN = fileN.rstrip(".py")
		
		# Look for the "spec" used to import the named module.
		# The "spec" contains the information needed to actually load the module
		spec = importlib.machinery.PathFinder.find_spec(fileN, path=[root])
		
		# If the module wasn't found, return false
		if not spec:
			return False
	
		# Otherwise, load the found module, and return it
		module = spec.loader.load_module()
		return module
	except Exception as e:
		show_exception_traceback()



"""
Function to parse arguments from command line
@return: args
"""
def parse_cli_args():
	import argparse
	parser = argparse.ArgumentParser(description='Main script to draw images from GPS points')
	parser.add_argument('-c','--config', help='Location of the configuration file', required=True, dest="config_loc")	
	args = parser.parse_args()

	return args

"""
Function to initialize and setup the logging functionality
"""
def init_log_file():
	with open(config.LOG.Path, 'w'):
		pass
	logging.basicConfig(filename=config.LOG.Path, level=logging.DEBUG)

"""
Function to optimize pool post treatment for team transfers between pool
"""
def optimize_pool_post_treatment_team_transfers(D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, oldResultId, userId, teamTransfers, flagPhantom, calculatedResult):
	try:
		# duplicate results
		results = calculatedResult
		
# 		logging.debug(" results: %s" %(json.dumps(results),))
		iter = config.INPUT.Iter

		typeMatch = results["typeMatch"]

		# add final flag to results
		if "params" in results:

			# change values concerning variation of team members per pool
			results["params"]["varEquipeParPoulePossible"] = 0
			
			# get team names 
			for scenario, contentScenario in teamTransfers.items():
				for teamTransfer in contentScenario:
					teamTransfer["equipeDepartNom"] = get_team_name_escaped_from_team_id(teamTransfer["equipeDepart"])
					teamTransfer["equipeDestinationNom"] = get_team_name_escaped_from_team_id(teamTransfer["equipeDestination"])
			
			# add team transfers to params
			results["params"]["changeAffectEquipes"] = teamTransfers
			
			
		########################################### Transfer team between pools #######################################
		for scenario, teamTransfers in teamTransfers.items():
			
			# get the corresponding data from the previous calculated result
			if scenario == "optimalSansContrainte":
				scenarioName = "scenarioOptimalSansContrainte"
			elif scenario == "equitableSansContrainte":
				scenarioName = "scenarioEquitableSansContrainte"
			elif scenario == "optimalAvecContrainte":
				scenarioName = "scenarioOptimalAvecContrainte"
			elif scenario == "equitableAvecContrainte":
				scenarioName = "scenarioEquitableAvecContrainte"

			resultsScenario = results[scenarioName]

			poulesIdOri  = resultsScenario["poulesId"]
			
			poulesIdResult = dict(poulesIdOri)
			
			for teamTransfer in teamTransfers:
			
				# remove and add parting team
				poulesIdResult[teamTransfer["pouleDepart"]].remove(int(teamTransfer["equipeDepart"]))
				poulesIdResult[teamTransfer["pouleDestination"]].append(int(teamTransfer["equipeDepart"]))
				
				# remove and add entering team
				poulesIdResult[teamTransfer["pouleDestination"]].remove(int(teamTransfer["equipeDestination"]))
				poulesIdResult[teamTransfer["pouleDepart"]].append(int(teamTransfer["equipeDestination"]))

				# sort leaving and destinatio pool
				poulesIdResult[teamTransfer["pouleDestination"]].sort()
				poulesIdResult[teamTransfer["pouleDepart"]].sort()
				
			# update pool ids
			resultsScenario["poulesId"] = poulesIdResult

			# get coordinates for each point in the pools
			poolDistributionCoords_scenario = get_coords_pool_distribution(poulesIdResult)
			results[scenarioName]["poulesCoords"] = poolDistributionCoords_scenario
		
			# get encounter list from pool distribution dict
			encounters_scenario = create_encounters_from_pool_distribution(poulesIdResult)
			results[scenarioName]["rencontreDetails"] = encounters_scenario
	 		
			# get pool details from encounters
			poolDetails_scenario = create_pool_details_from_encounters(encounters_scenario, poulesIdResult)
			results[scenarioName]["estimationDetails"] = poolDetails_scenario
		
			# get sum info from pool details
			sumInfo_scenario = get_sum_info_from_pool_details(poolDetails_scenario)
			results[scenarioName]["estimationGenerale"] = sumInfo_scenario
			
		return results

	except Exception as e:
		show_exception_traceback()


"""
Function to optimize pool post treatment for variation of team number per pool
"""	
def optimize_pool_post_treatment_var_team_nbr(D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, resultId, userId, varTeamNbrPerPool, flagPhantom, calculatedResult, P_InitMat_withConstraint, P_InitMat_oneWayWithConstraint):
	try:
		# duplicate results
		results = calculatedResult

		iter = config.INPUT.Iter

		typeMatch = results["typeMatch"]

		if typeMatch == "allerSimple":
			isOneWay = 1
		else:
			isOneWay = 0

		# add final flag to results
		if "params" in results:
			results["params"]["final"] = "oui"

			# change values concerning variation of team members per pool
			results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool
			results["params"]["varEquipeParPoulePossible"] = 0

		############# optimal scenario without constraint #################
		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")
		resultsOptimalWithoutConstraint = results["scenarioOptimalSansContrainte"]
		if resultsOptimalWithoutConstraint:
			
			poolDistribution_OptimalWithoutConstraint = variation_team_number_per_pool(resultsOptimalWithoutConstraint["poulesId"], varTeamNbrPerPool)
			
			# create P Matrix from pool distribution	
			P_Mat_OptimalWithoutConstraint = create_matrix_from_pool_distribution(poolDistribution_OptimalWithoutConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_OptimalWithoutConstraint = np.triu(P_Mat_OptimalWithoutConstraint)

			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#

			chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)

	 		# get pool distribution
			poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams)

			# eliminate phantom teams
			poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint

			infoPool = get_info_pool_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["params"]["infoPoule"] = infoPool

			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint
		
			# get encounter list from pool distribution dict
			encounters_OptimalWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint
	 		
			# get pool details from encounters
			poolDetails_OptimalWithoutConstraint = create_pool_details_from_encounters(encounters_OptimalWithoutConstraint, poolDistribution_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint
		
			# get sum info from pool details
			sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint)
			results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint

		############# equitable scenario without constraint #################
		resultsEquitableWithoutConstraint = results["scenarioEquitableSansContrainte"]
		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
		if resultsEquitableWithoutConstraint:

			poolDistribution_EquitableWithoutConstraint = variation_team_number_per_pool(resultsEquitableWithoutConstraint["poulesId"], varTeamNbrPerPool)
			
			# create P Matrix from pool distribution	
			P_Mat_EquitableWithoutConstraint = create_matrix_from_pool_distribution(poolDistribution_EquitableWithoutConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_EquitableWithoutConstraint = np.triu(P_Mat_EquitableWithoutConstraint)

			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#

			chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)
	
			# get pool distribution
			poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint
	
			# get encounter list from pool distribution dict
			encounters_EquitableWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint
	
			# get pool details from encounters
			poolDetails_EquitableWithoutConstraint = create_pool_details_from_encounters(encounters_EquitableWithoutConstraint, poolDistribution_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint
	
			# get sum info from pool details
			sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint)
			results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint

		############# optimal scenario with constraint #################
		logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
		resultsOptimalWithConstraint = results["scenarioOptimalAvecContrainte"]
		if resultsOptimalWithConstraint:

			poolDistribution_OptimalWithConstraint = variation_team_number_per_pool(resultsOptimalWithConstraint["poulesId"], varTeamNbrPerPool)
			
			# create P Matrix from pool distribution	
			P_Mat_OptimalWithConstraint = create_matrix_from_pool_distribution(poolDistribution_OptimalWithConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_OptimalWithConstraint = np.triu(P_Mat_OptimalWithConstraint)
				
			for iterLaunch in range(config.INPUT.IterLaunch):
				logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#

				if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
					P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
				# in case of failure because of constraints
				else:
					if typeMatch == "allerRetour":
						P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
					elif typeMatch == "allerSimple":
						P_Mat_OptimalWithConstraint = P_InitMat_oneWayWithConstraint

			chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)

			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)

			# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint

			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint

			# get encounter list from pool distribution dict
			encounters_OptimalWithConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint
		
			# get pool details from encounters
			poolDetails_OptimalWithConstraint = create_pool_details_from_encounters(encounters_OptimalWithConstraint, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint
	
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint


		############# equitable scenario with constraint #################
		logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
		resultsEquitableWithConstraint = results["scenarioEquitableAvecContrainte"]
		if resultsEquitableWithConstraint:

			poolDistribution_EquitableWithConstraint = variation_team_number_per_pool(resultsEquitableWithConstraint["poulesId"], varTeamNbrPerPool)
			
			# create P Matrix from pool distribution	
			P_Mat_EquitableWithConstraint = create_matrix_from_pool_distribution(poolDistribution_EquitableWithConstraint, teamNbr, teams)

			# filter upper triangular size in the case of one way match
			if typeMatch == "allerSimple":
				P_Mat_EquitableWithConstraint = np.triu(P_Mat_EquitableWithConstraint)

			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable
				P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#

				if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
					P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
				# in case of failure because of constraints
				else:
					if typeMatch == "allerRetour":
						P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					elif typeMatch == "allerSimple":
						P_Mat_EquitableWithConstraint = P_InitMat_oneWayWithConstraint

			chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
	
			# get pool distribution
			poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint

			# get encounter list from pool distribution dict
			encounters_EquitableWithConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint = create_pool_details_from_encounters(encounters_EquitableWithConstraint, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint

		return results

	except Exception as e:
		show_exception_traceback()
	
		
"""
Function to optimize pool for Round Trip Match (Match Aller Retour)
"""
def optimize_pool_round_trip_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom):
	try:
		results = {"typeMatch": "allerRetour", "nombrePoule": poolNbr, "taillePoule": poolSize, 
					"scenarioRef": {}, "scenarioOptimalSansContrainte": {}, "scenarioOptimalAvecContrainte": {}, 
					"scenarioEquitableSansContrainte": {}, "scenarioEquitableAvecContrainte": {}, "params": {}
					}

		isOneWay = 0

# 		# get list of ids, names and cities from entity table for prohibition constraints
		for indexProhibition, members in enumerate(prohibitionConstraints, start=1):
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "interdictions" not in results["params"]:
				results["params"]["interdictions"] = {indexProhibition: {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}
													}
			else: 
				results["params"]["interdictions"][indexProhibition] = {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}

		# get list of names and cities from entity table for type distribution constraints
		for teamType, members in typeDistributionConstraints.items():
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "repartitionsHomogenes" not in results["params"]:
				results["params"]["repartitionsHomogenes"] = {teamType: { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
															}
			else:
				results["params"]["repartitionsHomogenes"][teamType] = { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
				
		# save constraint variation of team number per pool
		results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool

		# based on phantom flag, save to results the possibility to make variation of team number per pool
		if flagPhantom:
			results["params"]["phantomExiste"] = 1
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]
			
		else:
			results["params"]["phantomExiste"] = 0
			results["params"]["varEquipeParPoulePossible"] = 1
			maxVarTeamNbrPerPool = poolSize - 2
			results["params"]["varEquipeParPouleProposition"] = list(range(0, maxVarTeamNbrPerPool+1 ))
			# limit variation of team member to max 2
			if len(results["params"]["varEquipeParPouleProposition"]) > 3:
				results["params"]["varEquipeParPouleProposition"] = results["params"]["varEquipeParPouleProposition"][:3]


		logging.debug(" ########################################## ROUND TRIP　MATCH ###############################################")
		iter = config.INPUT.Iter
		
		# add status constraints in the result
		if statusConstraints:
			results["contraintsExiste"] = 1
		else:
			results["contraintsExiste"] = 0


		logging.debug(" #################################### REFERENCE RESULT #################################################")
		returnPoolDistributionRef = create_reference_pool_distribution_from_db(teams, poolSize)
		
		# process only if there is a reference
		if returnPoolDistributionRef["status"] == "yes":
			
			# add boolean to results
			results["refExiste"] = 1
			
			poolDistributionRef = returnPoolDistributionRef["data"]

			# create P Matrix reference to calculate distance	
			P_Mat_ref = create_matrix_from_pool_distribution(poolDistributionRef, teamNbr, teams)
	
			chosenDistanceRef = calculate_V_value(P_Mat_ref, D_Mat)
	
			# eliminate phnatom teams
			poolDistributionRef = eliminate_phantom_in_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesId"] = poolDistributionRef
	
			# get coordinates for each point in the pools
			poolDistributionCoordsRef = get_coords_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesCoords"] = poolDistributionCoordsRef
	
			# get encounter list from pool distribution dict
			encountersRef = create_encounters_from_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["rencontreDetails"] = encountersRef
	
			# get pool details from encounters
			poolDetailsRef = create_pool_details_from_encounters(encountersRef, poolDistributionRef)
			results["scenarioRef"]["estimationDetails"] = poolDetailsRef
	
			# get sum info from pool details
			sumInfoRef = get_sum_info_from_pool_details(poolDetailsRef)
			results["scenarioRef"]["estimationGenerale"] = sumInfoRef
		else:
			# add boolean to results
			results["refExiste"] = 0

		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")

		# optimal scenario without constraint
		for iterLaunch in range(config.INPUT.IterLaunch):
			logging.debug(" -----------------------------   iterLaunch: %s -------------------------------------" %iterLaunch)
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
				else:
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#
# 
# 		P_Mat_OptimalWithoutConstraint = P_Mats_OptimalWithoutConstraint[P_Mat_chosenIndex]
		chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
	
# 		# get pool distribution
		poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
# 		
		# eliminate phnatom teams
		poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint
		
		# get real info without phantom teams (only for optimal scenario without constraint)
		infoPool = get_info_pool_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["params"]["infoPoule"] = infoPool
		
		# get coordinates for each point in the pools
		poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint
		
		# get encounter list from pool distribution dict
		encounters_OptimalWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint
 		
		# get pool details from encounters
		poolDetails_OptimalWithoutConstraint = create_pool_details_from_encounters(encounters_OptimalWithoutConstraint, poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint
	
		# get sum info from pool details
		sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint


		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
		# equitable scenario without constraint
		# launch calculation based on ref scenario only if the params are comparable
		for iterLaunch in range(config.INPUT.IterLaunch):
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
				else:
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#

		chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)

		# get pool distribution
		poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)

		# eliminate phnatom teams
		poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint

		# get coordinates for each point in the pools
		poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint

		# get encounter list from pool distribution dict
		encounters_EquitableWithoutConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint

		# get pool details from encounters
		poolDetails_EquitableWithoutConstraint = create_pool_details_from_encounters(encounters_EquitableWithoutConstraint, poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint

		# get sum info from pool details
		sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint


		if statusConstraints:
			logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
			# optimal scenario with constraint   
			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					# try to use ref scenario
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
								
					# if there is no ref scenario
					else:
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
				
				# for second iteration onwards
				else:
					P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
						P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_OptimalWithConstraint = P_InitMat_withConstraint


			chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
	 		
			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)
	
			# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint
	
			# get encounter list from pool distribution dict
			encounters_OptimalWithConstraint = create_encounters_from_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint
			
			# get pool details from encounters
			poolDetails_OptimalWithConstraint = create_pool_details_from_encounters(encounters_OptimalWithConstraint, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint
		
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint

			logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
	
			# equitable scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable

				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						# try to use ref scenario
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
					
					# if there is no ref scenario
					else:
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
				
				
				# for second iteration onwards
				else:
					P_Mat_EquitableWithConstraint = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
						P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
	
			chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
		
			# get pool distribution
			poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint

			# get encounter list from pool distribution dict
			encounters_EquitableWithConstraint = create_encounters_from_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint = create_pool_details_from_encounters(encounters_EquitableWithConstraint, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint


		return results 
	except Exception as e:
		show_exception_traceback()

"""
Function to optimize pool for One Way Match (Match Aller Simple)
"""
def optimize_pool_one_way_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom):
	try:
		results = {"typeMatch": "allerSimple", "nombrePoule": poolNbr, "taillePoule": poolSize, 
					"scenarioRef": {}, "scenarioOptimalSansContrainte": {}, "scenarioOptimalAvecContrainte": {}, 
					"scenarioEquitableSansContrainte": {}, "scenarioEquitableAvecContrainte": {}, "params": {}
				}
		isOneWay = 1

# 		# get list of ids, names and cities from entity table for prohibition constraints
		for indexProhibition, members in enumerate(prohibitionConstraints, start=1):
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "interdictions" not in results["params"]:
				results["params"]["interdictions"] = {indexProhibition: {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}
													}
			else: 
				results["params"]["interdictions"][indexProhibition] = {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}

		# get list of names and cities from entity table for type distribution constraints
		for teamType, members in typeDistributionConstraints.items():
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "repartitionsHomogenes" not in results["params"]:
				results["params"]["repartitionsHomogenes"] = {teamType: { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
															}
			else:
				results["params"]["repartitionsHomogenes"][teamType] = { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 

		# save constraint variation of team number per pool
		results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool

		# based on phantom flag, save to results the possibility to make variation of team number per pool
		if flagPhantom:
			results["params"]["phantomExiste"] = 1
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]
		else:
			results["params"]["phantomExiste"] = 0
			results["params"]["varEquipeParPoulePossible"] = 1
			maxVarTeamNbrPerPool = poolSize - 2
			results["params"]["varEquipeParPouleProposition"] = list(range(0, maxVarTeamNbrPerPool+1 ))
			# limit variation of team member to max 2
			if len(results["params"]["varEquipeParPouleProposition"]) > 3:
				results["params"]["varEquipeParPouleProposition"] = results["params"]["varEquipeParPouleProposition"][:3]


		logging.debug(" ########################################## ONE WAY　MATCH ###############################################")
		iter = config.INPUT.Iter
		
		# add status constraints in the result
		if statusConstraints:
			results["contraintsExiste"] = 1
		else:
			results["contraintsExiste"] = 0
		
		logging.debug(" #################################### REFERENCE RESULT #################################################")
		returnPoolDistributionRef = create_reference_pool_distribution_from_db(teams, poolSize)
		
		# process only if there is a reference
		if returnPoolDistributionRef["status"] == "yes":
			
			# add boolean to results
			results["refExiste"] = 1

			poolDistributionRef = returnPoolDistributionRef["data"]

			# create P Matrix reference to calculate distance	
			P_Mat_ref = create_matrix_from_pool_distribution(poolDistributionRef, teamNbr, teams)
	
			# take upper part of matrix
			P_Mat_ref = np.triu(P_Mat_ref)
	
# 			logging.debug(" P_Mat_ref: \n%s" %(P_Mat_ref,))
			chosenDistanceRef = calculate_V_value(P_Mat_ref, D_Mat)
	
			# eliminate phnatom teams
			poolDistributionRef = eliminate_phantom_in_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesId"] = poolDistributionRef
	
			# get coordinates for each point in the pools
			poolDistributionCoordsRef = get_coords_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesCoords"] = poolDistributionCoordsRef
	
			# get encounter list from pool distribution dict
			encountersRef = create_encounters_from_pool_distribution_one_way(poolDistributionRef)
			results["scenarioRef"]["rencontreDetails"] = encountersRef
	
			# get pool details from encounters
			poolDetailsRef = create_pool_details_from_encounters(encountersRef, poolDistributionRef)
			results["scenarioRef"]["estimationDetails"] = poolDetailsRef
	
			# get sum info from pool details
			sumInfoRef = get_sum_info_from_pool_details(poolDetailsRef)
			results["scenarioRef"]["estimationGenerale"] = sumInfoRef
		else:
			# add boolean to results
			results["refExiste"] = 0

		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")

		# optimal scenario without constraint
		for iterLaunch in range(config.INPUT.IterLaunch):
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)
				else:
					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#
					

		chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
	
# 		# get pool distribution
		poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams )
# 		
		# eliminate phnatom teams
		poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint
		
		# get real info without phantom teams (only for optimal scenario without constraint)
		infoPool = get_info_pool_from_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["params"]["infoPoule"] = infoPool
		
		
		# get coordinates for each point in the pools
		poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint
		
		# get encounter list from pool distribution dict
		encounters_OptimalWithoutConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint
		
		# get pool details from encounters
		poolDetails_OptimalWithoutConstraint = create_pool_details_from_encounters(encounters_OptimalWithoutConstraint, poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint
	
		# get sum info from pool details
		sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint

		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
		# equitable scenario without constraint

		for iterLaunch in range(config.INPUT.IterLaunch):
			# launch calculation based on ref scenario only if the params are comparable
			if iterLaunch == 0:
				# launch calculation based on ref scenario only if the params are comparable
				if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)
				else:
					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
			else:
				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#

		chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)

		# get pool distribution
		poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)

		# eliminate phnatom teams
		poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint

		# get coordinates for each point in the pools
		poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint

		# get encounter list from pool distribution dict
		encounters_EquitableWithoutConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint

		# get pool details from encounters
		poolDetails_EquitableWithoutConstraint = create_pool_details_from_encounters(encounters_EquitableWithoutConstraint, poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint

		# get sum info from pool details
		sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint

		if statusConstraints:
			logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
			# optimal scenario with constraint   
			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					# try to use ref scenario
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
								
					# if there is no ref scenario
					else:
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
				
				# for second iteration onwards
				else:
					P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
						P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_OptimalWithConstraint = P_InitMat_withConstraint


			chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
	 		
			poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)
	
				# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint

			# get encounter list from pool distribution dict
			encounters_OptimalWithConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint
			
			# get pool details from encounters
			poolDetails_OptimalWithConstraint = create_pool_details_from_encounters(encounters_OptimalWithConstraint, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint
		
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint

			logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
	
			# equitable scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunch):
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						# try to use ref scenario
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						else:
							# if error, launch again with P_init_matrix
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
					
					# if there is no ref scenario
					else:
						P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
				
				
				# for second iteration onwards
				else:
					P_Mat_EquitableWithConstraint = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
					if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
						P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
					# if error, use P_init_matrix
					else:
						P_Mat_EquitableWithConstraint = P_InitMat_withConstraint


	
			chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
		
			# get pool distribution
			poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix_one_way(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)

			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint

			# get encounter list from pool distribution dict
			encounters_EquitableWithConstraint = create_encounters_from_pool_distribution_one_way(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint = create_pool_details_from_encounters(encounters_EquitableWithConstraint, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint


		return results
	except Exception as e:
		show_exception_traceback()


"""
Function to optimize pool for Plateau Match (Match Plateau)
"""
def optimize_pool_plateau_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom, welcomeConstraintExistMatchPlateau):
	try:
		results = {"typeMatch": "plateau", "nombrePoule": poolNbr, "taillePoule": poolSize, 
					"scenarioRef": {}, "scenarioOptimalSansContrainte": {}, "scenarioOptimalAvecContrainte": {}, 
					"scenarioEquitableSansContrainte": {}, "scenarioEquitableAvecContrainte": {}, 
					"params": {"contrainteAccueilPlateauExiste" : welcomeConstraintExistMatchPlateau}
				}
		isOneWay = 0
		
# 		# get list of ids, names and cities from entity table for prohibition constraints
		for indexProhibition, members in enumerate(prohibitionConstraints, start=1):
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "interdictions" not in results["params"]:
				results["params"]["interdictions"] = {indexProhibition: {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}
													}
			else: 
				results["params"]["interdictions"][indexProhibition] = {"ids": prohibitionDetail["ids"], 
																		"noms": prohibitionDetail["names"], 
																		"villes": prohibitionDetail["cities"], 
																		}

		# get list of names and cities from entity table for type distribution constraints
		for teamType, members in typeDistributionConstraints.items():
			members = ",".join(map(str, members)) # convert list of ints to string
			prohibitionDetail = get_list_details_from_list_ids_for_entity(members)
			
			if "repartitionsHomogenes" not in results["params"]:
				results["params"]["repartitionsHomogenes"] = {teamType: { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 
															}
			else:
				results["params"]["repartitionsHomogenes"][teamType] = { "ids": prohibitionDetail["ids"], 
																		 "noms": prohibitionDetail["names"], 
																		 "villes": prohibitionDetail["cities"], 
																		 } 

		# save constraint variation of team number per pool
		results["params"]["varEquipeParPouleChoisi"] = varTeamNbrPerPool

		# based on phantom flag, save to results the possibility to make variation of team number per pool
		if flagPhantom:
			results["params"]["phantomExiste"] = 1
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]
			
		else:
			results["params"]["phantomExiste"] = 0
			results["params"]["varEquipeParPoulePossible"] = 0
			results["params"]["varEquipeParPouleProposition"] = [0]

		logging.debug(" ########################################## PLATEAU　MATCH ###############################################")

		iter = config.INPUT.Iter
		
		# add status constraints in the result
		if statusConstraints:
			results["contraintsExiste"] = 1
		else:
			results["contraintsExiste"] = 0

		logging.debug(" #################################### REFERENCE RESULT #################################################")
	
		# get info for reference scenario from DB
		returnRefScenarioPlateau =  get_ref_scenario_plateau(teams, userId, reportId)

		returnPoolDistributionRef = create_reference_pool_distribution_from_db(teams, poolSize)
		
		# process only if there is a reference
		if returnRefScenarioPlateau["status"] == "yes" and  returnPoolDistributionRef["status"] == "yes":
			
			# add boolean to results
			results["refExiste"] = 1

			encountersRefPlateau = returnRefScenarioPlateau["data"]
			results["scenarioRef"]["rencontreDetails"] = encountersRefPlateau

			poolDistributionRef = returnPoolDistributionRef["data"]

			chosenDistanceRefPlateau = calculate_distance_from_encounters_plateau(encountersRefPlateau)

			# create P Matrix reference to calculate distance	
			P_Mat_ref = create_matrix_from_pool_distribution(poolDistributionRef, teamNbr, teams)

			chosenDistanceRefPool = calculate_V_value(P_Mat_ref, D_Mat)

			# eliminate phnatom teams
			poolDistributionRef = eliminate_phantom_in_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesId"] = poolDistributionRef

			# get coordinates for each point in the pools
			poolDistributionCoordsRef = get_coords_pool_distribution(poolDistributionRef)
			results["scenarioRef"]["poulesCoords"] = poolDistributionCoordsRef

# 			# get pool details from encounters
			poolDetailsRefPlateau = create_pool_details_from_encounters_plateau(encountersRefPlateau, poolDistributionRef)
			results["scenarioRef"]["estimationDetails"] = poolDetailsRefPlateau

			# get sum info from pool details
			sumInfoRef = get_sum_info_from_pool_details(poolDetailsRefPlateau)
			results["scenarioRef"]["estimationGenerale"] = sumInfoRef
		else:
			# add boolean to results
			results["refExiste"] = 0

		logging.debug(" ####################### RESULT OPTIMAL WITHOUT CONSTRAINT #############################################")

		# optimize distance pool only if pool numer is more than 1
		if poolNbr > 1:
			# optimal scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunchPlateau):
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
					else:
	 					P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
				else:
	 				P_Mat_OptimalWithoutConstraint = get_p_matrix_for_round_trip_match_optimal_without_constraint(P_Mat_OptimalWithoutConstraint, D_Mat, iter, teamNbr)#
# 
			chosenDistance_OptimalWithoutConstraint = calculate_V_value(P_Mat_OptimalWithoutConstraint, D_Mat)
	
			# get pool distribution
			poolDistribution_OptimalWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithoutConstraint, teamNbr, poolNbr, poolSize, teams)

		# optimize distance pool only if pool numer is 1
		elif poolNbr == 1:
			poolDistribution_OptimalWithoutConstraint = {1: sorted(teams)}

		# eliminate phnatom teams
		poolDistribution_OptimalWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesId"] = poolDistribution_OptimalWithoutConstraint

		# get coordinates for each point in the pools
		poolDistributionCoords_OptimalWithoutConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithoutConstraint

		# optimize distance for each pool
		encounters_OptimalWithoutConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_OptimalWithoutConstraint, welcomeConstraintExistMatchPlateau)
		results["scenarioOptimalSansContrainte"]["rencontreDetails"] = encounters_OptimalWithoutConstraint_Plateau

		# get pool details from encounters
		poolDetails_OptimalWithoutConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_OptimalWithoutConstraint_Plateau, poolDistribution_OptimalWithoutConstraint)
		results["scenarioOptimalSansContrainte"]["estimationDetails"] = poolDetails_OptimalWithoutConstraint_Plateau
# 
		# get sum info from pool details
		sumInfo_OptimalWithoutConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithoutConstraint_Plateau)
		results["scenarioOptimalSansContrainte"]["estimationGenerale"] = sumInfo_OptimalWithoutConstraint

		logging.debug(" ####################### RESULT EQUITABLE WITHOUT CONSTRAINT ############################################")
	
		# optimize distance pool only if pool numer is more than 1
		if poolNbr > 1:
			# optimal scenario without constraint
			for iterLaunch in range(config.INPUT.IterLaunchPlateau):
				# launch calculation based on ref scenario only if the params are comparable
				if iterLaunch == 0:
					if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
						P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_ref, D_Mat, iter, teamNbr)#
					else:
	 					P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat_withoutConstraint, D_Mat, iter, teamNbr)#
				else:
	 				P_Mat_EquitableWithoutConstraint = get_p_matrix_for_round_trip_match_equitable_without_constraint(P_Mat_EquitableWithoutConstraint, D_Mat, iter, teamNbr)#
# 
			chosenDistance_EquitableWithoutConstraint = calculate_V_value(P_Mat_EquitableWithoutConstraint, D_Mat)
	
			# get pool distribution
			poolDistribution_EquitableWithoutConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithoutConstraint, teamNbr, poolNbr, poolSize, teams)
			

		# optimize distance pool only if pool numer is 1
		elif poolNbr == 1:
			poolDistribution_EquitableWithoutConstraint = {1: sorted(teams)}

		# eliminate phnatom teams
		poolDistribution_EquitableWithoutConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesId"] = poolDistribution_EquitableWithoutConstraint


		# get coordinates for each point in the pools
		poolDistributionCoords_EquitableWithoutConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithoutConstraint

		# optimize distance for each pool
		encounters_EquitableWithoutConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_EquitableWithoutConstraint, welcomeConstraintExistMatchPlateau)
		results["scenarioEquitableSansContrainte"]["rencontreDetails"] = encounters_EquitableWithoutConstraint_Plateau

		# get pool details from encounters
		poolDetails_EquitableWithoutConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_EquitableWithoutConstraint_Plateau, poolDistribution_EquitableWithoutConstraint)
		results["scenarioEquitableSansContrainte"]["estimationDetails"] = poolDetails_EquitableWithoutConstraint_Plateau

		# get sum info from pool details
		sumInfo_EquitableWithoutConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithoutConstraint_Plateau)
		results["scenarioEquitableSansContrainte"]["estimationGenerale"] = sumInfo_EquitableWithoutConstraint

		if statusConstraints:
			logging.debug(" ####################### RESULT OPTIMAL WITH CONSTRAINT #############################################")
		
			# optimize distance pool only if pool numer is more than 1
			if poolNbr > 1:
				# optimal scenario with constraint
				for iterLaunch in range(config.INPUT.IterLaunchPlateau):
					# launch calculation based on ref scenario only if the params are comparable
					if iterLaunch == 0:
						# try to use ref scenario
						if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							else:
								# if error, launch again with P_init_matrix
								P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
								if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
									P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
								# if error, use P_init_matrix
								else:
									P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
									
						# if there is no ref scenario
						else:
							P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
								P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_OptimalWithConstraint = P_InitMat_withConstraint
					
					# for second iteration onwards
					else:
						P_Mat_OptimalWithConstraintReturn = get_p_matrix_for_round_trip_match_optimal_with_constraint(P_Mat_OptimalWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_OptimalWithConstraintReturn["status"] == "yes":
							P_Mat_OptimalWithConstraint = P_Mat_OptimalWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_OptimalWithConstraint = P_InitMat_withConstraint

	
				chosenDistance_OptimalWithConstraint = calculate_V_value(P_Mat_OptimalWithConstraint, D_Mat)
		
				# get pool distribution
				poolDistribution_OptimalWithConstraint = create_pool_distribution_from_matrix(P_Mat_OptimalWithConstraint, teamNbr, poolNbr, poolSize, teams)

			# optimize distance pool only if pool numer is 1
			elif poolNbr == 1:
				poolDistribution_OptimalWithConstraint = {1: sorted(teams)}
	
			# eliminate phnatom teams
			poolDistribution_OptimalWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesId"] = poolDistribution_OptimalWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_OptimalWithConstraint = get_coords_pool_distribution(poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["poulesCoords"] = poolDistributionCoords_OptimalWithConstraint
	
			# optimize distance for each pool
			encounters_OptimalWithConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_OptimalWithConstraint, welcomeConstraintExistMatchPlateau)
			results["scenarioOptimalAvecContrainte"]["rencontreDetails"] = encounters_OptimalWithConstraint_Plateau
	
			# get pool details from encounters
			poolDetails_OptimalWithConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_OptimalWithConstraint_Plateau, poolDistribution_OptimalWithConstraint)
			results["scenarioOptimalAvecContrainte"]["estimationDetails"] = poolDetails_OptimalWithConstraint_Plateau
	
			# get sum info from pool details
			sumInfo_OptimalWithConstraint = get_sum_info_from_pool_details(poolDetails_OptimalWithConstraint_Plateau)
			results["scenarioOptimalAvecContrainte"]["estimationGenerale"] = sumInfo_OptimalWithConstraint

			logging.debug(" ######################### RESULT EQUITABLE WITH CONSTRAINT ############################################")
	
			# optimize distance pool only if pool numer is more than 1
			if poolNbr > 1:
				# equitable scenario with constraint
				for iterLaunch in range(config.INPUT.IterLaunchPlateau):

					# launch calculation based on ref scenario only if the params are comparable
					if iterLaunch == 0:
						if ( (returnPoolDistributionRef["status"] == "yes") and (returnPoolDistributionRef["poolNbrRef"] == poolNbr) and (returnPoolDistributionRef["maxPoolSizeRef"] == poolSize) ):
							# try to use ref scenario
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_ref, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							else:
								# if error, launch again with P_init_matrix
								P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
								if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
									P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
								# if error, use P_init_matrix
								else:
									P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
						
						
						# if there is no ref scenario
						else:
							P_Mat_EquitableWithConstraintReturn = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat_withConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
							if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
								P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
							# if error, use P_init_matrix
							else:
								P_Mat_EquitableWithConstraint = P_InitMat_withConstraint
					
					
					# for second iteration onwards
					else:
						P_Mat_EquitableWithConstraint = get_p_matrix_for_round_trip_match_equitable_with_constraint(P_Mat_EquitableWithConstraint, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay)#
						if P_Mat_EquitableWithConstraintReturn["status"] == "yes":
							P_Mat_EquitableWithConstraint = P_Mat_EquitableWithConstraintReturn["data"]
						# if error, use P_init_matrix
						else:
							P_Mat_EquitableWithConstraint = P_InitMat_withConstraint


				chosenDistance_EquitableWithConstraint = calculate_V_value(P_Mat_EquitableWithConstraint, D_Mat)
		
				# get pool distribution
				poolDistribution_EquitableWithConstraint = create_pool_distribution_from_matrix(P_Mat_EquitableWithConstraint, teamNbr, poolNbr, poolSize, teams)

			# optimize distance pool only if pool numer is 1
			elif poolNbr == 1:
				poolDistribution_EquitableWithConstraint = {1: sorted(teams)}
	
			# eliminate phnatom teams
			poolDistribution_EquitableWithConstraint = eliminate_phantom_in_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesId"] = poolDistribution_EquitableWithConstraint
	
			# get coordinates for each point in the pools
			poolDistributionCoords_EquitableWithConstraint = get_coords_pool_distribution(poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["poulesCoords"] = poolDistributionCoords_EquitableWithConstraint
	
			# optimize distance for each pool
			encounters_EquitableWithConstraint_Plateau = create_encounters_from_pool_distribution_plateau(poolDistribution_EquitableWithConstraint, welcomeConstraintExistMatchPlateau)
			results["scenarioEquitableAvecContrainte"]["rencontreDetails"] = encounters_EquitableWithConstraint_Plateau
	
			# get pool details from encounters
			poolDetails_EquitableWithConstraint_Plateau = create_pool_details_from_encounters_plateau(encounters_EquitableWithConstraint_Plateau, poolDistribution_EquitableWithConstraint)
			results["scenarioEquitableAvecContrainte"]["estimationDetails"] = poolDetails_EquitableWithConstraint_Plateau
	
			# get sum info from pool details
			sumInfo_EquitableWithConstraint = get_sum_info_from_pool_details(poolDetails_EquitableWithConstraint_Plateau)
			results["scenarioEquitableAvecContrainte"]["estimationGenerale"] = sumInfo_EquitableWithConstraint

		return results

	except Exception as e:
		show_exception_traceback()


"""
Main callback function which executes PyTreeRank ALgorithm
"""
def callback(ch, method, properties, body):
	try:
		
		beginTime = datetime.datetime.now()
		beginTimeStr = beginTime.strftime('%Y-%m-%d %H:%M:%S')
		logging.debug("starting current time : %s" %beginTimeStr)

		body = body.decode('utf-8')
		# get report id from RabbitMQ
		reportId = str(body)
		print("starting calculation for reportId: %s at %s" %(reportId, beginTimeStr))

		# update job status to 1 (running)
		update_job_status(reportId, 1)
		
		logging.debug("####################################### READ PARAMS FROM USER ##############################################")
		# get params from DB
		sql = "select id_groupe, type_action, params from parametres where id=%s"%reportId
		groupId, launchType, params = db.fetchone_multi(sql)
		
		# parse json
		params = json.loads(params)

		poolNbr = params["nbrPoule"]

		# get constraint variation team number per pool
		if "varEquipeParPoule" in params:
			varTeamNbrPerPool = int(params["varEquipeParPoule"])
		else:
			varTeamNbrPerPool = 0

		# get team transfer params per pool
		if "changeAffectEquipes" in params:
			teamTransfers = params["changeAffectEquipes"]
		else:
			teamTransfers = {}

		iterConstraint = config.INPUT.IterConstraint

		# flag to indicate if there are phantom teams (used if the pool size is a float instead of an int)
		flagPhantom = 0
		
		# get welcome constraint for match plateau
		if "contrainteAccueilPlateauExiste" in params:
			welcomeConstraintExistMatchPlateau = params["contrainteAccueilPlateauExiste"]
		else:
			welcomeConstraintExistMatchPlateau = 0

		logging.debug("########################################### READ DATA FROM DB ##############################################")
		# get user id 
		sql = "select id_utilisateur from groupe where id=%s"%groupId

		userId = db.fetchone(sql)

		# get entites from DB
		sql = "select equipes from groupe where id=%d" %groupId

		# convert list of strings to list of ints
		teams = sorted(list(map(int, db.fetchone(sql).split(","))))
		teamNbr = len(teams)
		
		# check if teams have ref scenario or not
		withRef = check_existence_ref_scenario(teams)
		
		# check team number and pool number for match plateau
		if launchType == "plateau":
			control_params_match_plateau(userId, teamNbr, poolNbr, reportId)
		
		
		# update teams and other params by including phantom teams
		teamsWithPhantom = list(teams)
		teamNbrWithPhantom = len(teamsWithPhantom)
		phantomTeamNbr = 0


		# get prohibition constraints
		returnProhibitionConstraints = get_prohibition_constraints(params['interdictions'])
		if returnProhibitionConstraints["status"] == "yes":
			statusProhibitionConstraints = True
			prohibitionConstraints = returnProhibitionConstraints["data"]
		else:
			statusProhibitionConstraints = False
			prohibitionConstraints = []
		

		# get type distribution constraints
		returnTypeDistributionConstraints = get_type_distribution_constraints(params['repartitionHomogene'])
		if returnTypeDistributionConstraints["status"] == "yes":
			statusTypeDistributionConstraints = True
			typeDistributionConstraints = returnTypeDistributionConstraints["data"]
		else:
			statusTypeDistributionConstraints = False
			typeDistributionConstraints = {}
 
		# get status for constraints existence
		statusConstraints = statusProhibitionConstraints or statusTypeDistributionConstraints

		logging.debug("########################################### CALCULATE POOL SIZE #############################################")
		# Manage case where there is not enough teams to make even distribution of pools
		if teamNbr%poolNbr == 0:
			poolSize = int(teamNbr/poolNbr)
		else:
			flagPhantom = 1
			poolSize = 	int(teamNbr/poolNbr)+1
			teamNbrWithPhantom = poolNbr * poolSize
			phantomTeamNbr = teamNbrWithPhantom - teamNbr
			
			# add random phantom number
			for i in range(phantomTeamNbr):
				while True:
					phantomMemberId = -random.randint(1,1000)
					if phantomMemberId not in teamsWithPhantom:
						teamsWithPhantom.append(phantomMemberId)
						break

		logging.debug("####################################### CREATE DISTANCE MATRIX ##############################################")
		D_Mat = create_distance_matrix_from_db(teams, reportId, userId)

		# modify the distance matrix if there are phantom members (add zeros columns and rows) 
		if flagPhantom == 1:
			D_Mat = create_phantom_distance_matrix(D_Mat, teamNbr, poolNbr, poolSize)
			
		logging.debug("####################################### CREATE INITIALISATION MATRIX ########################################")
		P_InitMat_withoutConstraint = create_init_matrix_without_constraint(teamNbrWithPhantom, poolNbr, poolSize)

		# get P_Init Mat for one way
		P_InitMat_oneWaywithoutConstraint = np.triu(P_InitMat_withoutConstraint)

		# create init matrix with constraint if there is any constraint
		if statusConstraints:
			statusCreateInitMatrix = create_init_matrix_with_constraint(teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, iterConstraint, prohibitionConstraints, typeDistributionConstraints)
# 
			if statusCreateInitMatrix["success"]:
				P_InitMat_withConstraint = statusCreateInitMatrix["data"]
				P_InitMat_oneWayWithConstraint = np.triu(P_InitMat_withConstraint)
				
			else:
				
				# try to create init matrix manually
				statusCreateInitMatrixManual = create_init_matrix_with_constraint_manual(teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, iterConstraint, prohibitionConstraints, typeDistributionConstraints)
				
				if statusCreateInitMatrixManual["success"]:
					P_InitMat_withConstraint = statusCreateInitMatrixManual["data"]
					P_InitMat_oneWayWithConstraint = np.triu(P_InitMat_withConstraint)
				else:
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
					logging.debug("Failure to create P Init Matrix which fulfills all constraints")
					send_email_to_user_failure(userId, reportId)
					logging.debug("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! ERROR !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!")
		else:
			P_InitMat_withConstraint = None
			P_InitMat_oneWayWithConstraint = None

		logging.debug("####################################### COMPARE DISTANCES TWO WAY AND ONE WAY ###############################")
		distanceInitRoundTrip = calculate_V_value(P_InitMat_withoutConstraint, D_Mat)

		logging.debug("############################################# OPTIMIZE POOL #################################################")
		### Pre treatment
		if launchType == "allerRetour" and varTeamNbrPerPool == 0 and not teamTransfers:
			results = optimize_pool_round_trip_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom)
		elif launchType == "allerSimple" and varTeamNbrPerPool == 0 and not teamTransfers:
			results = optimize_pool_one_way_match(P_InitMat_oneWaywithoutConstraint, P_InitMat_oneWayWithConstraint, D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom)
		elif launchType == "plateau" and varTeamNbrPerPool == 0 and not teamTransfers:
			results = optimize_pool_plateau_match(P_InitMat_withoutConstraint, P_InitMat_withConstraint, D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, userId, varTeamNbrPerPool, flagPhantom, welcomeConstraintExistMatchPlateau)

		### check values for params teamTransfer and varTeamNbrPerPool
		check_request_validity_post_treatment(teamTransfers, varTeamNbrPerPool, userId, reportId)

		### Post treatment variation of team number
		if varTeamNbrPerPool > 0 and ( launchType in  ["allerRetour", "allerSimple"] and not teamTransfers):
			logging.debug("############################################# POST TREATMENT VARIATION OF TEAM NUMBER #########################################")
			# get old result id 
			oldResultId = params["idAncienResultat"]
			
			sql = "select details_calcul from resultats where id=%s"%oldResultId
			calculatedResult = json.loads(db.fetchone(sql))
			
			# check whether it is a final result (the variation of team members per pool has already been performed)
			check_final_result(calculatedResult, userId, reportId)

			# check given params if they are the same or not as the stocked params
			check_given_params_post_treatment(calculatedResult, launchType, poolNbr, prohibitionConstraints, typeDistributionConstraints, userId, reportId)

			results = optimize_pool_post_treatment_var_team_nbr(D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, oldResultId, userId, varTeamNbrPerPool, flagPhantom, calculatedResult, P_InitMat_withConstraint, P_InitMat_oneWayWithConstraint)

		### Post treatment team transfers between pools
		if varTeamNbrPerPool == 0 and ( launchType in  ["allerRetour", "allerSimple"] and  teamTransfers):
			logging.debug("############################################# POST TREATMENT TEAM TRANSFERS #########################################")

			# get old result id 
			oldResultId = params["idAncienResultat"]
			
			sql = "select details_calcul from resultats where id=%s"%oldResultId
			calculatedResult = json.loads(db.fetchone(sql))

			# check whether it is a final result (the variation of team members per pool has already been performed)
			check_final_result(calculatedResult, userId, reportId)

			# check given params if they are the same or not as the stocked params
			check_given_params_post_treatment(calculatedResult, launchType, poolNbr, prohibitionConstraints, typeDistributionConstraints, userId, reportId)

			results = optimize_pool_post_treatment_team_transfers(D_Mat, teamNbrWithPhantom, poolNbr, poolSize, teamsWithPhantom, prohibitionConstraints, typeDistributionConstraints, iterConstraint, statusConstraints, reportId, oldResultId, userId, teamTransfers, flagPhantom, calculatedResult)


		if varTeamNbrPerPool == 0 and not teamTransfers:
			logging.debug("############################################# INSERT RESULT INTO DB #########################################")
			resultId = save_result_to_db(launchType, reportId, groupId, results)
		else:
			logging.debug("############################################# INSERT RESULT INTO DB #########################################")
			resultId = save_result_to_db_post_treatment(launchType, reportId, groupId, results)

		logging.debug("############################################# SEND EMAIL ####################################################")
		send_email_to_user(userId, resultId)
		logging.debug("################################################## FINISHED #################################################")

		# update job status to 2 (finished)
		update_job_status(reportId, 2)

		endTime = datetime.datetime.now()
		endTimeStr = endTime.strftime('%Y-%m-%d %H:%M:%S')
		
		logging.debug("finishing current time : %s" %endTimeStr)
		processingTime = endTime - beginTime
		processingTimeSeconds = processingTime.seconds
		logging.debug("processing time : %s seconds" %processingTimeSeconds)

		# insert calculation time to DB
		insert_calculation_time_to_db(userId, beginTime, endTime, processingTimeSeconds)

		# ack message
		ch.basic_ack(delivery_tag = method.delivery_tag)

		print("finish calculation for reportId: %s at %s" %(reportId, endTimeStr))


	except Exception as e:
		show_exception_traceback()
	finally:
		gc.collect()
		db.disconnect()
		sys.exit()



"""
Main function
"""
def main():
	global config, consumer

	try:
		# parse cli arguments
		args = parse_cli_args()
	
		# get config.py location entered by user
		config_loc = args.config_loc
		print ("config file: %s" %config_loc)
	
		# import config module using absolute path	#  
		config = absImport(config_loc)
	
		# Init log file
		init_log_file()

		# rabbitmq connection
		credentials = pika.PlainCredentials(config.MQ.User, config.MQ.Password)
		parameters = pika.ConnectionParameters(host=config.MQ.Host, credentials=credentials, heartbeat_interval=0)

		# synchronous RabbitMQ
		connection = pika.BlockingConnection(parameters)
		channel = connection.channel()
		channel.exchange_declare(exchange=config.MQ.Exchange, durable=True)
		channel.queue_declare(queue=config.MQ.Queue, durable=True)
		channel.queue_bind(config.MQ.Queue, config.MQ.Exchange, routing_key=None)
		channel.basic_qos(prefetch_count=1)
		
		channel.basic_consume(callback, queue=config.MQ.Queue, no_ack=False)
		channel.start_consuming()
		print (' [*] Waiting for messages. To exit press CTRL+C')


	except KeyboardInterrupt:
		consumer.stop()
	except Exception as e:
		show_exception_traceback()
	finally:
		gc.collect()
		db.disconnect()

if __name__ == "__main__":
	main()
