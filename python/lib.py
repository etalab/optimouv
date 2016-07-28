import sys, os
import logging
import db
import numpy as np
import random
import time
import json
import config
import smtplib
from email.mime.text import MIMEText
import requests
import datetime
import math
from email.header import Header
from email.utils import formataddr


"""
Function to print exception traceback
useful for debugging purposes
"""
def show_exception_traceback(reportId = None):
	exc_type, exc_value, exc_tb = sys.exc_info()
	fname = os.path.split(exc_tb.tb_frame.f_code.co_filename)[1]
	print("############################################ EXCEPTION OCCURRED ####################################################")
	print("Error Class: %s" %exc_type)
	print("Error Detail: %s " %exc_value)
	print("Filename: %s" %fname)
	print("Line number: %s " %exc_tb.tb_lineno)

	currentTimeStr = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
	logging.error("")
	logging.error("################################# EXCEPTION OCCURRED AT %s  #######################################"%currentTimeStr)
	logging.error("Error Class: %s" %exc_type)
	logging.error("Error Detail: %s " %exc_value)
	logging.error("Filename: %s" %fname)
	logging.error("Line number: %s " %exc_tb.tb_lineno)
	
	if reportId:
		update_job_status(reportId, -1)

	sys.exit()

"""
Function to calculate V value from matrix (2D array)
"""
def calculate_V_value(input_P_mat, input_D_mat):
	try:
		outputDistance = 0
	
		rowSize = input_P_mat.shape[0]
		colSize = input_P_mat.shape[1]
		
		for indexRow in range(rowSize):
			for indexColumn in range(colSize):
				P_mat_value = input_P_mat[indexRow, indexColumn]
				D_mat_value = input_D_mat[indexRow, indexColumn] 
				
				if D_mat_value == None:
					D_mat_value = 0
				
				outputDistance += (P_mat_value * D_mat_value ) 
		return outputDistance
	except Exception as e:
		show_exception_traceback()

"""
Function to calculate V value for equitable scenario from matrix (2D array)
"""
def calculate_V_value_equitable(input_P_mat, input_D_mat):
	try:
		distances = []
	
		rowSize = input_P_mat.shape[0]
		colSize = input_P_mat.shape[1]
		
		for indexRow in range(rowSize):
			distanceRow = 0
			for indexColumn in range(colSize):
				P_mat_value = input_P_mat[indexRow, indexColumn]
				D_mat_value = input_D_mat[indexRow, indexColumn] 
				
				if D_mat_value == None:
					D_mat_value = 0
				
				distanceRow += (P_mat_value * D_mat_value )
			distances.append(distanceRow)
		return max(distances)
	except Exception as e:
		show_exception_traceback()


"""
Function to adjust pool distribution based on variation of team number per pool
"""
def adjust_pool_attribution_based_on_pool_variation(teamPoolResult, poolNbr, poolSize, varTeamNbrPerPool):
	try:
		teamPoolResultTransformed = [] 

		# determine if pool number is even or odd
		if poolNbr % 2 == 0:
			poolNbrCategory = "even"
		else:
			poolNbrCategory = "odd"
			
		# treat differently according to the pool number category
		if poolNbrCategory == "even":

			# create dictionary to transform pool
			transformDict = {}
			for pool in range(1, poolNbr+1):
				# variation for odd numbered member
				if pool%2 == 1:
					transformDict[pool] = poolSize - varTeamNbrPerPool
				# variation for even numbered member
				elif pool%2 == 0:
					transformDict[pool] = poolSize + varTeamNbrPerPool
			
			# change members in all pools
			for pool in teamPoolResult:
				# determine the number of pool size of the current pool
				currentPoolSize = teamPoolResultTransformed.count(pool)

				if currentPoolSize < transformDict[pool]:
					teamPoolResultTransformed.append(pool)
				else:
					# affect to the next pool
					pool = pool +1
					teamPoolResultTransformed.append(pool)

		elif poolNbrCategory == "odd":

			# create dictionary to transform pool
			transformDict = {}
			for pool in range(1, poolNbr+1):
				# for last pool (no variation)
				if pool == poolNbr:
					transformDict[pool] = poolSize
				# variation for odd numbered member
				elif pool%2 == 1:
					transformDict[pool] = poolSize - varTeamNbrPerPool
				# variation for even numbered member
				elif pool%2 == 0:
					transformDict[pool] = poolSize + varTeamNbrPerPool
			
			# change members in all pools except the last pool
			for pool in teamPoolResult:
				if pool == poolNbr:
					teamPoolResultTransformed.append(pool)
				else:
					# determine the number of pool size of the current pool
					currentPoolSize = teamPoolResultTransformed.count(pool)

					if currentPoolSize < transformDict[pool]:
						teamPoolResultTransformed.append(pool)
					else:
						# affect to the next pool
						pool = pool +1
						teamPoolResultTransformed.append(pool)
						
		
		return teamPoolResultTransformed
	except Exception as e:
		show_exception_traceback()

"""
Function to create pool distribution from P Matrix
"""
def create_pool_distribution_from_matrix(P_Mat, teamNbr, poolNbr, poolSize, teams):
	try:

		# Dict containing the distribution of groups in the pools
		poolDistribution = {}
	
		tempPools = []
		performanceCounter = 0
		assignedTeams = []
		for indexRow, teamDepart in enumerate(teams):
			# break if tempPools has reached number of desired pools
			if len(tempPools)  == poolNbr:
				break
			
			# continue to the next row if teamDepart is already in the list of assigned teams
			if teamDepart in assignedTeams:
				continue

			# get the row content
			rowContent = list(P_Mat[indexRow])

			# calculate the pool size of the row
			poolSizeRow = rowContent.count(1.0) + 1

			tempPool = [] # create a temporary pool (this pool has max size of poolSizeRow)
			tempPool.append(teamDepart) # add first element in the pool

			for indexCol, teamDestination in enumerate(teams):
				# continue to the next row if teamDepart is already in the list of assigned teams
				if teamDestination in assignedTeams:
					continue

				valueMat = int(P_Mat[indexRow][indexCol])
	
				performanceCounter += 1
	
				# add teamDestination to temporary pool if the pool size has not been reached and if the teamDestination is not yet in temporary pool 
				if ( len(tempPool) < poolSizeRow) and (teamDestination not in tempPool) and (valueMat == 1):
					tempPool.append(teamDestination)
					
				# if the pool size has been reached, push the tempPool to tempPools
				if len(tempPool) == poolSizeRow:
					tempPool = sorted(tempPool)
					if tempPool not in tempPools:
						
						if len(tempPools) < poolNbr:
							tempPools.append(tempPool)
							assignedTeams.extend(tempPool)
						else: 
							break
				

		firstPoolName = ord('A')
		# obtain group distribution per pool
		for pool in range(poolNbr):
			poolDistribution[pool+1] = tempPools[pool]
	
		# calculate efficiency of the algorithm
		efficiency = round((performanceCounter*100/teamNbr/teamNbr), 2)
	

		return poolDistribution

	except Exception as e:
		show_exception_traceback()

"""
Function to create pool distribution from P Matrix
"""
def create_pool_distribution_from_matrix_one_way(P_Mat, teamNbr, poolNbr, poolSize, teams):
	try:

		# Dict containing the distribution of groups in the pools
		poolDistribution = {}
	
		tempPools = []
		performanceCounter = 0 # counter which indicates the performance of the algorithm
		assignedTeams = [] # list of all assigned teams
		for indexRow, teamDepart in enumerate(teams):
			# break if tempPools has reached number of desired pools
			if len(tempPools)  == poolNbr:
				break
			
			# continue to the next row if teamDepart is already in the list of assigned teams
			if teamDepart in assignedTeams:
				continue

			# get the row content
			rowContent = list(P_Mat[indexRow])

			# calculate the pool size of the row
			poolSizeRow = rowContent.count(1.0) + 1

			# move to the next row if the pool size is smalller than expected
			if(poolSizeRow == poolSize):
				tempPool = [] # create a temporary pool (this pool has max size of poolSizeRow)
				tempPool.append(teamDepart) # add first element in the pool
	
				for indexCol, teamDestination in enumerate(teams):
					# continue to the next row if teamDepart is already in the list of assigned teams
					if teamDestination in assignedTeams:
						continue
	
					valueMat = int(P_Mat[indexRow][indexCol])
		
					performanceCounter += 1
		
					# add teamDestination to temporary pool if the pool size has not been reached and if the teamDestination is not yet in temporary pool 
					if ( len(tempPool) < poolSizeRow) and (teamDestination not in tempPool) and (valueMat == 1):
						tempPool.append(teamDestination)
						
					# if the pool size has been reached, push the tempPool to tempPools
					if len(tempPool) == poolSizeRow:
						tempPool = sorted(tempPool)
						if tempPool not in tempPools:
							
							if len(tempPools) < poolNbr:
								tempPools.append(tempPool)
								assignedTeams.extend(tempPool)
							else: 
								break
				

		firstPoolName = ord('A')
		# obtain group distribution per pool
		for pool in range(poolNbr):
			poolDistribution[pool+1] = tempPools[pool]
	
		# calculate efficiency of the algorithm
		efficiency = round((performanceCounter*100/teamNbr/teamNbr), 2)
	
		return poolDistribution

	except Exception as e:
		show_exception_traceback()


"""
Function to convert decimal to base 3
"""
def convert_decimal_to_base3 (n):
	try:
		if n == 0:
			return '0'
		nums = []
		while n:
			n, r = divmod(n, 3)
			nums.append(str(r))
		return ''.join(reversed(nums))
	except Exception as e:
		show_exception_traceback()

"""
Function to get combination from base3
"""
def get_host_combination_index_from_base3(combinationInput, base3Tmp):
	try:

		combinationOutput = {}
		for day, combinationPerDay in combinationInput.items():
			
			fromIndex = (day-1)*3
			toIndex = ((day-1)*3)+2
			
			combinationPerDayInput = base3Tmp[fromIndex:toIndex+1]
			
			combinationOutput[day] = [int(value) for value in combinationPerDayInput]
		
		return combinationOutput
	except Exception as e:
		show_exception_traceback()

"""
Function to get host names from Plateau distribution Per pool
"""
def get_member_combination_ids_from_host_combination_index(plateauDistributionPerPool, combination):
	try:
		memberCombinationIds = {}
	
		for day, contentDay in plateauDistributionPerPool.items():
			memberCombinationIds[day] = {}
			
			for indexGroup, group in enumerate(contentDay):
				hostIndex = combination[day][indexGroup]
				hostId = group[hostIndex]
				
				# get member ids
				memberIds = list(group)
				if hostId in memberIds:
					memberIds.remove(hostId)
				
				distanceGroup = 0
				
				for memberId in memberIds:
					sql = "select distance from trajet where depart=%s and destination=%s"%(memberId, hostId)
					distanceGroup += int(db.fetchone(sql))
				
				memberCombinationIds[day][indexGroup+1] = {"hostId": hostId, "memberIds": memberIds, "distanceGroup": distanceGroup}
			
		
		return memberCombinationIds
			
	except Exception as e:
		show_exception_traceback()

"""
Function to get distance for a certain member combination ids
"""
def get_distance_for_member_combination_ids(memberCombinationIds):
	try:
		distanceMemberCombination = 0
		
		for day, contentDay in memberCombinationIds.items():
			for group, contentGroup in contentDay.items():
				distanceMemberCombination += contentGroup["distanceGroup"]
		
		return distanceMemberCombination
	except Exception as e:
		show_exception_traceback()

"""
Function to check if all teams welcome at least one time 
@return: 0 if false
@return: 1 if true
"""
def check_welcome_constraint_match_plateau(memberCombinationIds, teams):
	try:
		statusCheckWelcomeConstraint = 0
		welcomingTeams = []
		
		for day, contentDay in memberCombinationIds.items():
			for group, contentGroup in contentDay.items():
				hostIdTmp = int(contentGroup["hostId"])
				if hostIdTmp not in welcomingTeams:
					welcomingTeams.append(hostIdTmp) 
		
		# compare list of welcoming teams with all teams
		if sorted(welcomingTeams) == teams:
			statusCheckWelcomeConstraint = 1

		return statusCheckWelcomeConstraint
	except Exception as e:
		show_exception_traceback()

"""
Function to check if each team receives max two times (three times is an error) 
@return: 0 if false
@return: 1 if true
"""
def check_max_times_host_match_plateau(memberCombinationIds):
	try:
		statusMaxTimesHost = 0


		receivingHosts = {}
		
		# get the statistics about the receiving hosts
		for day, contentDay in memberCombinationIds.items():
			for group, contentGroup in contentDay.items():
				if "hostId" in contentGroup:
					hostId = contentGroup["hostId"]
					
					if hostId not in receivingHosts:
						receivingHosts[hostId] = 1
					else:
						receivingHosts[hostId] += 1
						
						# return false if there is any member who receives more than two times
						if receivingHosts[hostId] > 2:
							return statusMaxTimesHost
		
		# set status to true
		statusMaxTimesHost = 1

		return statusMaxTimesHost

	except Exception as e:
		show_exception_traceback()

"""
Function to calculate distance plateau for a given 3x4 matrix (plateau distribution)
"""
def calculate_shortest_distance_plateau_from_3_4_matrix(plateauDistributionPerPool, welcomeConstraintExistMatchPlateau, teams):
	try:

		# initialize host combination
		hostCombinationIndex = {}
		for day, contentDay in plateauDistributionPerPool.items():
			hostCombinationIndex[day] = []
			for indexGroup, group in enumerate(contentDay, start=1):
				hostCombinationIndex[day].append(0)
		
		# calculate the total number of  host combination
		hostCombinationNbr = 1
		for day, contentDay in plateauDistributionPerPool.items():
			for indexGroup, group in enumerate(contentDay, start=1):
				hostCombinationNbr *= len(group)

		# find the shortest distance
		bestHostCombinationIndex = {}
		bestMemberCombinationIds = {}
		bestDistanceMemberCombination = 0
		bestIterationNbr = 0
		for i in range(hostCombinationNbr):

			if i == config.INPUT.IterShortestDistancePlateau:
				break
			
			# convert iteration number to base 3
			base3Tmp = str(convert_decimal_to_base3(i))

			# complete base3 to 12 characters (12 of 3-elements tuples )
			for k in range(12-len(base3Tmp)):
				base3Tmp = '0' + base3Tmp

			# get combination of index host for each day  
			hostCombinationIndex = get_host_combination_index_from_base3(hostCombinationIndex, base3Tmp)

			# get host names from combination
			memberCombinationIds = get_member_combination_ids_from_host_combination_index(plateauDistributionPerPool, hostCombinationIndex)
			
			# if the flag of welcome constraint is checked
			if(int(welcomeConstraintExistMatchPlateau) == 1):
				# check welcome constraint for match plateau 
				
				statusCheckWelcomeConstraintMatchPlateau = check_welcome_constraint_match_plateau(memberCombinationIds, teams)
				
				if statusCheckWelcomeConstraintMatchPlateau == 0:
					continue
			
			# each member can only become host at most txo times (two days)
			statusCheckMaxTimesHost = check_max_times_host_match_plateau(memberCombinationIds)
			
			if statusCheckMaxTimesHost == 0:
				continue
			
			
			# calculate distance total for a specific member combination
			distanceMemberCombination = get_distance_for_member_combination_ids(memberCombinationIds)

			# assign the value for the first time
			if bestDistanceMemberCombination == 0:
				bestDistanceMemberCombination = distanceMemberCombination
				bestHostCombinationIndex = hostCombinationIndex
				bestMemberCombinationIds = memberCombinationIds
				
			# compare with current best value
			if distanceMemberCombination < bestDistanceMemberCombination:
				bestDistanceMemberCombination = distanceMemberCombination
				bestHostCombinationIndex = hostCombinationIndex
				bestMemberCombinationIds = memberCombinationIds
				bestIterationNbr = i


		result = {	"bestDistance": bestDistanceMemberCombination, 
					"bestHostCombinationIndex": bestHostCombinationIndex,
					"bestMemberCombinationIds": bestMemberCombinationIds,
					}
		return result
		
	except Exception as e:
		show_exception_traceback()

"""
Function to get encounters details from member combination ids
"""
def get_encounters_details_from_member_combination_ids(memberCombinationIds):
	try:
		encountersDetailsPlateau = {}

		for day, contentDay in memberCombinationIds.items():
			encountersDetailsPlateau[day] = []
			
			for group, contentGroup in contentDay.items():
				groupTmp = {
							"hoteId": contentGroup["hostId"],
							"premierEquipeId": contentGroup["memberIds"][0],
							"deuxiemeEquipeId": contentGroup["memberIds"][1],
							"distanceGroupe": contentGroup["distanceGroup"],
							
						}
				
				# get city name and postal code of host
				sql = "select ville, nom, code_postal from entite where id=%s"%contentGroup["hostId"]
				hostCity, hostName, hostPostalCode =   db.fetchone_multi(sql)
				groupTmp["hoteVille"] = hostCity.replace("'", u"''")
				groupTmp["hoteNom"] = hostName.replace("'", u"''")
				groupTmp["hoteCodePostal"] = hostPostalCode

				
				# get city name and postal code of first team
				sql = "select ville, nom, code_postal, participants from entite where id=%s"%contentGroup["memberIds"][0]
				firstTeamCity, firstTeamName, firstTeamPostalCode, firstTeamParticipantsNbr =   db.fetchone_multi(sql)
				groupTmp["premierEquipeVille"] = firstTeamCity.replace("'", u"''")
				groupTmp["premierEquipeNom"] = firstTeamName.replace("'", u"''")
				groupTmp["premierEquipeCodePostal"] = firstTeamPostalCode
				groupTmp["nbrParticipants"] = firstTeamParticipantsNbr
				

				# get city name and postal code of second team
				sql = "select ville, nom, code_postal from entite where id=%s"%contentGroup["memberIds"][1]
				secondTeamCity, secondTeamName, secondTeamPostalCode =   db.fetchone_multi(sql)
				groupTmp["deuxiemeEquipeVille"] = secondTeamCity.replace("'", u"''")
				groupTmp["deuxiemeEquipeNom"] = secondTeamName.replace("'", u"''")
				groupTmp["deuxiemeEquipeCodePostal"] = secondTeamPostalCode

				# get distance for all participants
				groupTmp["distanceGroupeTousParticipants"] = groupTmp["distanceGroupe"] * groupTmp["nbrParticipants"]

				# get travel time for the group
				travelTime = 0
				travelIds = []
				travellNames = []
				for memberId in contentGroup["memberIds"]:
					sql = "select duree from trajet where depart=%s and destination=%s"%(memberId, contentGroup["hostId"])
					travelTime += int(db.fetchone(sql))
					travelIds.append([contentGroup["hostId"], memberId])

					sql = "select ville from entite where id=%s"%memberId
					memberName = db.fetchone(sql)
					travellNames.append([hostName.replace("'", u"''"), memberName.replace("'", u"''") ] )
				groupTmp["dureeGroupe"] = travelTime
				groupTmp["deplacementsIds"] = travelIds
				groupTmp["deplacementsNoms"] = travellNames

				encountersDetailsPlateau[day].append(groupTmp)
			
		return encountersDetailsPlateau

	except Exception as e:
		show_exception_traceback()


"""
Function to create encounters from pool distribution for match plateau
"""
def create_encounters_from_pool_distribution_plateau(poolDistribution, welcomeConstraintExistMatchPlateau):
	try:
		encountersPlateau = {}

		bestDistancePerPool = {}
		for pool, teams in poolDistribution.items():
			encountersPlateau[pool] = {}

			# init vars
			bestDistancePerPool[pool] = 0
			bestMemberCombinationIds = {}
			for i in range(config.INPUT.IterPlateau):

				# assign random value for each team
				teamRandomValues = [round(random.random() * 100) for i in range(len(teams))]
	
				# get the index values of the sorted random values
				indexSortedTeamRandomValues = list(range(1, len(teamRandomValues)+1))
				indexSortedTeamRandomValues = sorted( indexSortedTeamRandomValues, key=lambda k: teamRandomValues[indexSortedTeamRandomValues.index(k)] )
	
	
				# assign teams based on their random number values according to the established matrix
				firstTeamAssigned = teams[indexSortedTeamRandomValues.index(1)]
				secondTeamAssigned = teams[indexSortedTeamRandomValues.index(2)]
				thirdTeamAssigned = teams[indexSortedTeamRandomValues.index(3)]
				fourthTeamAssigned = teams[indexSortedTeamRandomValues.index(4)]
				fifthTeamAssigned = teams[indexSortedTeamRandomValues.index(5)]
				sixthTeamAssigned = teams[indexSortedTeamRandomValues.index(6)]
				seventhTeamAssigned = teams[indexSortedTeamRandomValues.index(7)]
				eighthTeamAssigned = teams[indexSortedTeamRandomValues.index(8)]
				ninthTeamAssigned = teams[indexSortedTeamRandomValues.index(9)]
	
				# temporary plateau distribution per pool
				plateauDistributionPerPoolTmp = {	1: [ 	[ firstTeamAssigned, secondTeamAssigned, thirdTeamAssigned ], 
															[ fourthTeamAssigned, fifthTeamAssigned, sixthTeamAssigned ], 
															[ seventhTeamAssigned, eighthTeamAssigned, ninthTeamAssigned] ],
													2: [ 	[ thirdTeamAssigned, fifthTeamAssigned, eighthTeamAssigned ],
															[ firstTeamAssigned, sixthTeamAssigned, ninthTeamAssigned ], 
															[ secondTeamAssigned, fourthTeamAssigned, seventhTeamAssigned] ],
													3: [ 	[ firstTeamAssigned, fourthTeamAssigned, eighthTeamAssigned], 
															[ thirdTeamAssigned, sixthTeamAssigned, seventhTeamAssigned], 
															[ secondTeamAssigned, fifthTeamAssigned, ninthTeamAssigned] ],
													4: [ 	[ thirdTeamAssigned, fourthTeamAssigned, ninthTeamAssigned], 
															[ secondTeamAssigned, sixthTeamAssigned, eighthTeamAssigned], 
															[ firstTeamAssigned, fifthTeamAssigned, seventhTeamAssigned] ],
												}
	
				returnShortestDistance = calculate_shortest_distance_plateau_from_3_4_matrix(plateauDistributionPerPoolTmp, welcomeConstraintExistMatchPlateau, teams)
				
				# for first iteration
				if i == 0:
					bestDistancePerPool[pool] = returnShortestDistance["bestDistance"]
					bestMemberCombinationIds = returnShortestDistance["bestMemberCombinationIds"]

				# for second onward iterations
				else:
					if returnShortestDistance["bestDistance"] < bestDistancePerPool[pool]:
						bestDistancePerPool[pool] = returnShortestDistance["bestDistance"]
						bestMemberCombinationIds = returnShortestDistance["bestMemberCombinationIds"]
		

			# get encounter details from member combination ids
			encountersDetailsPlateauPerPool = get_encounters_details_from_member_combination_ids(bestMemberCombinationIds)
			
			encountersPlateau[pool] = encountersDetailsPlateauPerPool
			

		return encountersPlateau 

	except Exception as e:
		show_exception_traceback()



"""
Function to create encounters from pool distribution
"""
def create_encounters_from_pool_distribution(poolDistribution):
	try:
		encounters = {}
		
		for pool, members in poolDistribution.items():
			encounters[pool] = {}
			encounterNbr = 0
			for member1 in members:
				for member2 in members:
					if member1 != member2:
						encounterNbr += 1

						# calculate distance and travel time
						sql = "select distance, duree from trajet where depart=%s and destination=%s" %(member1, member2)
						distance, travelTime = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member1
						nbrParticipants1, name1, city1, postalCode1 = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member2
						nbrParticipants2, name2, city2, postalCode2 = db.fetchone_multi(sql)
		
						distanceAllParticipants = int(distance) * int(nbrParticipants1)
		
						# Escape single apostrophe for name and city
						name1 = name1.replace("'", u"''")
						name2 = name2.replace("'", u"''")
						city1 = city1.replace("'", u"''")
						city2 = city2.replace("'", u"''")

						encounter = {"equipeDepartId": member1, "equipeDestinationId": member2, 
														"distance": distance, "duree": travelTime,
														"nbrParticipants": nbrParticipants1, "distanceTousParticipants": distanceAllParticipants,
														"equipeDepartNom": name1, "equipeDestinationNom": name2,
														"equipeDepartVille": city1, "equipeDestinationVille": city2,
														"equipeDepartCodePostal": postalCode1, "equipeDestinationCodePostal": postalCode2
														
														}
						encounters[pool][encounterNbr] = encounter

		return encounters

	except Exception as e:
		show_exception_traceback()


"""
Function to create encounters from pool distribution
"""
def create_encounters_from_pool_distribution_one_way(poolDistribution):
	try:
		encounters = {}
		
		for pool, members in poolDistribution.items():
			encounters[pool] = {}
			encounterNbr = 0
			encountersTmp = [] # list of possible encounter combinations
			for member1 in members:
				for member2 in members:
					firstCombination = [member1, member2]
					secondCombination = [member2, member1]

					if (member1 != member2) and (firstCombination not in encountersTmp) and (secondCombination not in encountersTmp):
						encountersTmp.append(firstCombination)
						encountersTmp.append(secondCombination)
					
						encounterNbr += 1

						# calculate distance and travel time
						sql = "select distance, duree from trajet where depart=%s and destination=%s" %(member1, member2)
						distance, travelTime = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member1
						nbrParticipants1, name1, city1, postalCode1 = db.fetchone_multi(sql)

						sql = "select participants, nom, ville, code_postal from entite where id=%s" %member2
						nbrParticipants2, name2, city2, postalCode2 = db.fetchone_multi(sql)
		
						distanceAllParticipants = int(distance) * int(nbrParticipants1)
		
						# Escape single apostrophe for name and city
						name1 = name1.replace("'", u"''")
						name2 = name2.replace("'", u"''")
						city1 = city1.replace("'", u"''")
						city2 = city2.replace("'", u"''")

						encounter = {"equipeDepartId": member1, "equipeDestinationId": member2, 
														"distance": distance, "duree": travelTime,
														"nbrParticipants": nbrParticipants1, "distanceTousParticipants": distanceAllParticipants,
														"equipeDepartNom": name1, "equipeDestinationNom": name2,
														"equipeDepartVille": city1, "equipeDestinationVille": city2,
														"equipeDepartCodePostal": postalCode1, "equipeDestinationCodePostal": postalCode2
														
														}
						encounters[pool][encounterNbr] = encounter

		return encounters

	except Exception as e:
		show_exception_traceback()

"""
Function to create pool details from encounters
"""
def create_pool_details_from_encounters(encounters, poolDistribution):
	try:
		poolDetails = {}
		
		for pool, encountersDetails in encounters.items():
			poolDetails[pool] = {"distanceTotale": 0, "dureeTotale": 0, "distanceTotaleTousParticipants": 0, "nbrParticipantsTotal": 0}
		
			members = poolDistribution[pool]
			
			# get sum of participants for each team member
			for member in members:
				sql = "select participants from entite where id=%s" %member
				nbrParticipants = db.fetchone(sql)
				poolDetails[pool]["nbrParticipantsTotal"] += int(nbrParticipants)
		
			# get sum of other details
			for encounterNbr, encounterDetails in encountersDetails.items():
				poolDetails[pool]["distanceTotale"] += encounterDetails["distance"]
				poolDetails[pool]["dureeTotale"] += encounterDetails["duree"]
				poolDetails[pool]["distanceTotaleTousParticipants"] += encounterDetails["distanceTousParticipants"]
		
		return poolDetails
		
		
	except Exception as e:
		show_exception_traceback()

"""
Function to create pool details from encounters for match plateau
"""
def create_pool_details_from_encounters_plateau(encountersPlateau, poolDistribution):
	try:
		poolDetailsPlateau = {}
	
		for pool, contentPool in encountersPlateau.items():
			poolDetailsPlateau[pool] = {	"distanceTotale": 0,
											"dureeTotale": 0,
											"nbrParticipantsTotal": 0,
											"distanceTotaleTousParticipants": 0,
											}
			teamsIds = poolDistribution[pool]
			
			
			for teamId in teamsIds: 
				sql = "select participants from entite where id=%s"%teamId
				nbrParticipants = int(db.fetchone(sql))
				poolDetailsPlateau[pool]["nbrParticipantsTotal"] += nbrParticipants
			
			for day, contentDay in contentPool.items():
				for contentGroup in contentDay:
					poolDetailsPlateau[pool]["distanceTotale"] += contentGroup["distanceGroupe"]
					poolDetailsPlateau[pool]["dureeTotale"] += contentGroup["dureeGroupe"]
					poolDetailsPlateau[pool]["distanceTotaleTousParticipants"] += contentGroup["distanceGroupeTousParticipants"]
				

		return poolDetailsPlateau

	except Exception as e:
		show_exception_traceback()

"""
Function to get the sum of all info from pool details
"""
def get_sum_info_from_pool_details(poolDetails):
	try:
		sumInfo = {}
		
		for pool, poolContent in poolDetails.items():
			for info, infoValue in poolContent.items():
				if info in sumInfo:
					sumInfo[info] +=  infoValue
				else:
					sumInfo[info] =  infoValue
		
		return sumInfo
		
	except Exception as e:
		show_exception_traceback()



"""
Function to get indexes of prohibition constraints
"""
# def getIndexesProhibitionConstraints(prohibitionConstraints, teams):
def get_indexes_prohibition_constraints(prohibitionConstraints, teams):
	try:
		indexesProhibitionConstraints = []

		for constraint in prohibitionConstraints:
			member1 = int(constraint[0])
			member2 = int(constraint[1])
			indexesTmp = [ teams.index(member1), teams.index(member2) ]
			indexesProhibitionConstraints.append(indexesTmp)

		return indexesProhibitionConstraints
	except Exception as e:
		show_exception_traceback()
		
		
"""
Function to get indexes of type distribution constraints
"""
# def getIndexesTypeDistributionConstraints(typeDistributionConstraints, teams):
def get_indexes_type_distribution_constraints(typeDistributionConstraints, teams):
	try:
		indexesTypeDistributionConstraints = {}
		

		for type, constraint in typeDistributionConstraints.items():
			indexesTmp = []
			for member in constraint:
				indexesTmp.append(teams.index(int(member)))
			indexesTypeDistributionConstraints[type] = indexesTmp

		return indexesTypeDistributionConstraints	
	except Exception as e:
		show_exception_traceback()



"""
Function to create rules for prohibition constraints
"""
def create_rules_for_prohibition_constraints(indexesProhibitionConstraints, P_Mat):
	try:
		rulesConstraints = []

		# create list of prohibited transfer i j based on indexes of prohibition constraints
		for index in indexesProhibitionConstraints:
			member1 = index[0]
			member2 = index[1]
		
			# get current team members of member1
			membersOf1 = P_Mat[member1]
			indexesMembersOf1 = list(np.where(membersOf1 == 1)[0])
			
			# get current team members member2
			membersOf2 = P_Mat[member2]
			indexesMembersOf2 = list(np.where(membersOf2 == 1)[0])

			# create prohibition rules
			rulesMember1 = [] # member 1 with current team members of member2
			rulesMember2 = [] # member 2 with current team members of member1

			# between member1 and current team members of member2
			for indexMemberOf2 in indexesMembersOf2:
				listTemp = sorted([member1, indexMemberOf2])
				rulesMember1.append(listTemp) 

			# between member2 and current team members of member1
			for indexMemberOf1 in indexesMembersOf1:
				listTemp = sorted([member2, indexMemberOf1])
				rulesMember2.append(listTemp) 

			# concatenate the two rules
			rulesConstraint = rulesMember1 + rulesMember2

			rulesConstraints += rulesConstraint
		
		return rulesConstraints
		
	except Exception as e:
		show_exception_traceback()


"""
Function to get P Matrix for Round Trip Match Optimal Scenario Without Constraint
"""
def get_p_matrix_for_round_trip_match_optimal_without_constraint(P_InitMat, D_Mat, iter,  teamNbr):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value(P_InitMat, D_Mat)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance

		for nbIter in range(iter):
	
			# Function T_value
			T_Value *= 0.99
	
			### get index to change row and column
			while True:
				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				if i <= j and int(P_ij) == 0:
					break
	# 			
			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 
			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			deltaV = V_oriValue - V_transValue
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance
	
			if deltaV <= 0:
				pass
			else:
				randValue = random.random()
	
				expValue = math.exp(-deltaV/T_Value)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		return P_InitMat
		
	except Exception as e:
		show_exception_traceback()


"""
Function to get P Matrix for Round Trip and One Way Match Optimal Scenario With Constraints
"""
def get_p_matrix_for_round_trip_match_optimal_with_constraint(P_InitMat, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value(P_InitMat, D_Mat)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance

		for nbIter in range(iter):
	
			# Function T_value
			T_Value *= 0.99

			### get index to change row and column
			while True:
				
				if iterConstraint == 0:
					logging.error("Failure to create interchange rows and  columns (i, j) which fulfills all constraints ")
					
					return {"status": "no", "data": {}}
					
				iterConstraint -= 1
				
				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				# break if the constraints are satisfied
				# P_ij == 0 means that both teams are in different pool 
				if i <= j and int(P_ij) == 0:
					# apply prohibition constraints
						
					##### apply type distribution constraints #####
					# create temporary P matrix if the transIndex is applied 
					P_TransMatTmp = np.copy(P_InitMat)
					P_TransMatTmp[transIndex,:] = P_TransMatTmp[list(reversed(transIndex)),:]  # change two columns according to transIndex
					P_TransMatTmp[:,transIndex] = P_TransMatTmp[:,list(reversed(transIndex))] # change two rows according to transIndex

					if isOneWay == 1:
						poolDistributionTmp = create_pool_distribution_from_matrix_one_way(P_TransMatTmp, teamNbr, poolNbr, poolSize, teams)
					else:
						poolDistributionTmp = create_pool_distribution_from_matrix(P_TransMatTmp, teamNbr, poolNbr, poolSize, teams)

					statusProhibitionConstraints = check_prohibition_constraints(prohibitionConstraints, poolDistributionTmp)

					statusTypeDistributionConstraints = check_type_distribution_constraints(typeDistributionConstraints, poolDistributionTmp)

					# if the transformed matrix fulfills the type distribution constraints
					if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
						break
		
			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]

			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
	
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
	
			deltaV = V_oriValue - V_transValue
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance

			if deltaV <= 0:
				pass
			else:
				randValue = random.random()
	
				expValue = math.exp(-deltaV/T_Value)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		return {"status": "yes", "data": P_InitMat}
	
		
	except Exception as e:
		show_exception_traceback(reportId)



"""
Function to get P Matrix for Round Trip and One Way Match Equitable Scenario Without Constraint
"""
def get_p_matrix_for_round_trip_match_equitable_without_constraint(P_InitMat, D_Mat, iter,  teamNbr):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value_equitable(P_InitMat, D_Mat)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance

		for nbIter in range(iter):
	
			# Function T_value
			T_Value *= 0.99
	
			### get index to change row and column
			while True:
				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				if i <= j and int(P_ij) == 0:
					break
	# 			
			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 
			V_oriValue_equitable = calculate_V_value_equitable(P_InitMat, D_Mat)
	
			V_transValue_equitable = calculate_V_value_equitable(P_TransMat, D_Mat)
	
			deltaV_equitable = V_oriValue_equitable - V_transValue_equitable
			
			######################################################################################################
			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			deltaV = V_oriValue - V_transValue
			######################################################################################################
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance
	# 
			if deltaV_equitable <= 0:
				pass
			else:
				randValue = random.random()
	
				expValue = math.exp(-deltaV_equitable/T_Value)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		return P_InitMat
		
	except Exception as e:
		show_exception_traceback()



"""
Function to get P Matrix for Round Trip and One Way Match Equitable Scenario With Constraint
"""
def get_p_matrix_for_round_trip_match_equitable_with_constraint(P_InitMat, D_Mat, iter, teamNbr, poolNbr, poolSize, teams, prohibitionConstraints, typeDistributionConstraints, iterConstraint, reportId, userId, isOneWay):
	try:		
		# calculate initial distance
		initDistance = calculate_V_value_equitable(P_InitMat, D_Mat)
	
		# calculate initial T_Value
		T_Value = 0.1 * initDistance
	
		for nbIter in range(iter):
	
			# Function T_value
			T_Value *= 0.99
	
			### get index to change row and column
			while True:
				if iterConstraint == 0:
					logging.error("Failure to create interchange rows and  columns (i, j) which fulfills all constraints")

					return {"status": "no", "data": {}}
					
				iterConstraint -= 1

				transIndex = random.sample(range(teamNbr), 2)
			
				i = transIndex[0]
				j = transIndex[1]
				P_ij = P_InitMat[i][j]
			
				# break if the constraints are satisfied
				# P_ij == 0 means that both teams are in different pool 
				if i <= j and int(P_ij) == 0:
					# apply prohibition constraints

						##### apply type distribution constraints #####
						# create temporary P matrix if the transIndex is applied 
						P_TransMatTmp = np.copy(P_InitMat)
						P_TransMatTmp[transIndex,:] = P_TransMatTmp[list(reversed(transIndex)),:]  # change two columns according to transIndex
						P_TransMatTmp[:,transIndex] = P_TransMatTmp[:,list(reversed(transIndex))] # change two rows according to transIndex

						if isOneWay == 1:
							poolDistributionTmp = create_pool_distribution_from_matrix_one_way(P_TransMatTmp, teamNbr, poolNbr, poolSize, teams)
						else:	
							poolDistributionTmp = create_pool_distribution_from_matrix(P_TransMatTmp, teamNbr, poolNbr, poolSize, teams)

						statusProhibitionConstraints = check_prohibition_constraints(prohibitionConstraints, poolDistributionTmp)

						statusTypeDistributionConstraints = check_type_distribution_constraints(typeDistributionConstraints, poolDistributionTmp)

						# if the transformed matrix fulfills the type distribution constraints
						if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
							break
						

			P_TransMat = np.copy(P_InitMat)
	
			# change two columns according to transIndex
			P_TransMat[transIndex,:] = P_TransMat[list(reversed(transIndex)),:]
			# change two rows according to transIndex
			P_TransMat[:,transIndex] = P_TransMat[:,list(reversed(transIndex))]
	# 
			V_oriValue_equitable = calculate_V_value_equitable(P_InitMat, D_Mat)
	
			V_transValue_equitable = calculate_V_value_equitable(P_TransMat, D_Mat)
	
			deltaV_equitable = V_oriValue_equitable - V_transValue_equitable
			
# 			######################################################################################################
			V_oriValue = calculate_V_value(P_InitMat, D_Mat)
			V_transValue = calculate_V_value(P_TransMat, D_Mat)
			deltaV = V_oriValue - V_transValue
# 			######################################################################################################
			
			# temperature function 
			# reinitialize temperature in the middle of loop
			if nbIter == int(iter/2):
				T_Value = 0.1 * initDistance
			# reinitilize temperature if deltaV is at least 5% of V_oriValue
			if deltaV >= 0.05 * V_oriValue:
				T_Value = 0.1 * initDistance
	# 
			if deltaV_equitable <= 0:
				pass
			else:
				randValue = random.random()
	
				expValue = math.exp(-deltaV_equitable/T_Value)
	
				if randValue <= expValue:
					pass
				else:
					P_InitMat = P_TransMat

		return {"status": "yes", "data": P_InitMat}
		
	except Exception as e:
		show_exception_traceback(reportId)

"""
Function to eliminate phantom team members in pool distribution
"""
def eliminate_phantom_in_pool_distribution(poolDistribution):
	try:
		poolDistributionReturn = {}
		
		for pool, contentPool in poolDistribution.items():
			# remove phantom teams
			testPhantomId = any(i < 0 for i in contentPool)
			
			if testPhantomId:
# 			if -1 in contentPool:
				teamMembers = []
				for member in contentPool:
					if member > 0:
						teamMembers.append(member)

				poolDistributionReturn[pool] = teamMembers
			else:
				poolDistributionReturn[pool] = contentPool
		
		return poolDistributionReturn
	except Exception as e:
		show_exception_traceback()


"""
Function to get info pool (pool size and team numbers in each pool) even when there is any phantom teams
"""
def get_info_pool_from_pool_distribution(poolDistribution):
	try:
		
		infoPool = {}

		for pool, members in poolDistribution.items():
			poolSize = len(members)
			if poolSize not in infoPool:
				infoPool[poolSize] = 1
			else:
				infoPool[poolSize] += 1

		return infoPool

	except Exception as e:
		show_exception_traceback()



"""
Function to check existence of ref scenario
"""
def check_existence_ref_scenario(teams):
	try:
		withRef = False

		for teamId in teams:
			sql = "select poule from entite where id=%s"%teamId
			poule = db.fetchone(sql)
			
			if poule != '' and poule != None:
				return True

		
		return withRef
		
	except Exception as e:
		show_exception_traceback()

"""
Function to get reference pool distribution from DB
"""
def create_reference_pool_distribution_from_db(teams, poolSize):
	try:
		poolDistributionReference = {"status": "yes", "data": {}}
		phantomTeams = []


		listChars = []

		# construct pool distribution without phantom teams
		for team in teams:
			# check if not phantom team (separate beween normal teams and phantom teams)
			if int(team) > 0:
				sql = "select id, poule from entite where id=%s"%team
				teamId, poolId = db.fetchone_multi(sql)
	
				# check if there is poolId, if not exist return function
				if (poolId is None) or (poolId == ""):
					poolDistributionReference["status"] = "no"
					return poolDistributionReference

				#############################################################################################################
				# Patch for front, convert from pool letter given by users to number # FIXME !!!!
				if poolId not in listChars:
					listChars.append(poolId)
				poolId = (listChars.index(poolId)) + 1
				#############################################################################################################


				if poolId not in poolDistributionReference["data"]:
					poolDistributionReference["data"][poolId] = [teamId]
				else:
					poolDistributionReference["data"][poolId].append(teamId)
			else:
				phantomTeams.append(team)

		
		# calculate the max pool size if the distribution is not uniform (there are phantom members)
		maxPoolSizeRef = 0
		poolNbrRef = 0
		for pool, members in poolDistributionReference["data"].items():
			poolNbrRef += 1
			if maxPoolSizeRef < len(members):
				maxPoolSizeRef = len(members)
		poolDistributionReference["maxPoolSizeRef"] = maxPoolSizeRef
		poolDistributionReference["poolNbrRef"] = poolNbrRef

		# add phantom teams to the created distribution 
		# in the case of pool size in ref scenario is the same as the pool size specified by user
		if len(phantomTeams) > 0:
			poolDistributionReferenceTmp = dict.copy(poolDistributionReference["data"])
			for pool, poolTeams in poolDistributionReferenceTmp.items():
				if len(poolTeams) < maxPoolSizeRef:
					sizeDiff = maxPoolSizeRef - len(poolTeams)
					for i in range(sizeDiff):
						phantomTeam = phantomTeams.pop()
						poolDistributionReference["data"][pool].append(phantomTeam)
			
		return poolDistributionReference
	except Exception as e:
		show_exception_traceback()


"""
Function to get coordinates for longitude and latitude 
"""
def get_coordinates_from_city_id(entityId):

	try:
		# first get from table entite
		sql = "select latitude, longitude, id_ville_france from entite where id=%s"%entityId
		lat, lon, cityId = db.fetchone_multi(sql)
		
		# if not found try in table villes_france_free
		if (lat in [None, 0]) or (lon in [None, 0] ) and cityId != None:
			sql = "select ville_latitude_deg, ville_longitude_deg from villes_france_free where ville_id=%s"%cityId
			lat, lon  = db.fetchone_multi(sql)
			
			# update coordinates in table entite
			sql = "update entite set longitude=%s, latitude=%s where id=%s" %(lon, lat, entityId)
			db.execute(sql)
			db.commit()
			
		coord = "%s,%s"%(lat, lon)
		return coord

	except Exception as e:
		show_exception_traceback()
		
"""
Function to probe HERE web service and fill in table trajet
"""
def get_distance_travel_time_from_here_ws(cityIdDepart, cityIdDestination, coordDepart, coordDestination, reportId, userId, channel, method):
	try:
		
		hereUrl = "http://route.api.here.com/routing/7.2/calculateroute.json"
		params = { 	"waypoint0": coordDepart,
					"waypoint1": coordDestination,
					"app_id": config.HERE.RouteAppId,
					"app_code": config.HERE.RouteAppCode,
					"mode": "fastest;car;traffic:disabled"
				}
		senderAccount = config.EMAIL.From

		resp = requests.get(url=hereUrl, params=params)
		data = json.loads(resp.text)
					
		# get distance from HERE response
		if data["response"]:
			
			# if license error
			if "type" in data["response"]:
				if data["response"]["type"] == "SystemError":
					reportName = get_report_name_from_report_id(reportId)
					contentText = u"Bonjour,\n\n" 
					contentText += u"Optimouv rencontre un problème de licence HERE.\n"
					contentText += u"Veuillez contacter votre administrateur système.\n\n"
					contentText += u"Cordialement,\n\n"
					contentText += u"L'équipe d’Optimouv\n"
					contentText += u"%s"%(senderAccount)
					send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)
			else:
			
				distance = data['response']['route'][0]['summary']['distance']
				travelTime = data['response']['route'][0]['summary']['baseTime']

		# insert to table trajet
		dateCreation = datetime.datetime.now().date()

		try:
			
			sql = """insert into trajet (depart, destination, distance, duree, date_creation) 
						values( %(depart)s, %(destination)s, %(distance)s, %(duree)s, '%(date_creation)s' ) 
					"""%{
							"depart": cityIdDepart,
							"destination": cityIdDestination,
							"distance": distance,
							"duree": travelTime,
							"date_creation": dateCreation,
						}
			db.execute(sql)
			db.commit()
		except Exception as e:
			logging.error("Insertion error to table trajet, details %s" %(e))
			sys.exit()


		returnDict = {"distance": distance, "travelTime": travelTime}
		
		return returnDict
	
	except Exception as e:
		show_exception_traceback(reportId)
		


"""
Function to get discipline and federation id from user id
"""
def get_discipline_and_federation_id(userId):
	try:
		# get discipline and federation id
		sql = "select id_discipline from fos_user where id=%s"%(userId)
		disciplineId = db.fetchone(sql)
		
		sql = "select id_federation from discipline where id=%s"%(disciplineId)
		federationId = db.fetchone(sql)
		
		return (disciplineId, federationId)
		
	except Exception as e:
		show_exception_traceback()

"""
Function to create distance matrix from DB
"""
def create_distance_matrix_from_db(teams, reportId, userId, channel, method):
	try:
		# get size for the matrix
		teamNbr = len(teams)
		
		# Initialize Distance matrix D_Mat
		D_Mat = np.zeros((teamNbr, teamNbr))
		
		# number of HERE requests
		nbrRequestsHere = 0
		
		# get discipline and federation id
		disciplineId, federationId = get_discipline_and_federation_id(userId)
		
		
		# fill in the distance matrix
		for indexDepart, depart in enumerate(teams):
		
			# get destination cities
			destinations = list(teams) # make a copy of teams list
			
			for indexDestination, destination in enumerate(destinations):
				
				# do nothing if depart = destination
				if depart == destination:
					distance = 0
				else:
					# get distance from table trajet
					sql = "select distance from trajet where depart=%s and destination=%s "%(depart, destination)
					distance = db.fetchone(sql)
					
					# call HERE server if distance is None (not found in the table trajet)
					if distance == None:
						
						## get latitude and longitude for the depart team
						coordDepart = get_coordinates_from_city_id(depart)
						
						## get latitude and longitude for the destination team
						coordDestination = get_coordinates_from_city_id(destination)
						
						# increment number of HERE requests
						nbrRequestsHere += 1
						
						# get distance and travel time from HERE web service
						resultsHere = get_distance_travel_time_from_here_ws(depart, destination, coordDepart, coordDestination, reportId, userId, channel, method)

						# get distance from results Here
						distance = resultsHere["distance"]
				D_Mat[indexDepart][indexDestination] = distance
	
		if nbrRequestsHere > 0:
			try:
				sql = """INSERT INTO  statistiques_date (date_creation, type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
						VALUES (curdate(), '%(type_statistiques)s', %(id_utilisateur)s, %(id_discipline)s, %(id_federation)s, %(valeur)s)
						on duplicate key UPDATE valeur=valeur+VALUES(valeur);
					"""%{
							"type_statistiques": "nombreRequetesHere",
							"id_utilisateur": userId,
							"id_discipline": disciplineId,
							"id_federation": federationId,
							"valeur": nbrRequestsHere
						
						}
				db.execute(sql)
				db.commit()

			except Exception as e:
				logging.error("Insertion error to table statistiques_date, details %s" %(e))
				sys.exit()

		return D_Mat

	except Exception as e:
		show_exception_traceback(reportId)


"""
Function to insert calculation time to DB
"""
def insert_calculation_time_to_db(userId, startTime, endTime, duration):
	try:
		# get discipline and federation id
		disciplineId, federationId = get_discipline_and_federation_id(userId)

		sql = """INSERT INTO  statistiques_date_temps (temps_debut, temps_fin, type_statistiques, id_utilisateur, id_discipline, id_federation, valeur)
				VALUES ('%(temps_debut)s', '%(temps_fin)s', '%(type_statistiques)s', %(id_utilisateur)s, %(id_discipline)s, %(id_federation)s, %(valeur)s)
				on duplicate key UPDATE valeur=valeur+VALUES(valeur);
			"""%{
					"temps_debut": startTime.strftime('%Y-%m-%d %H:%M:%S'), 
					"temps_fin": endTime.strftime('%Y-%m-%d %H:%M:%S'),
					"type_statistiques": "tempsCalculOptiPoule",
					"id_utilisateur": userId,
					"id_discipline": disciplineId,
					"id_federation": federationId,
					"valeur": duration
				
				}
		db.execute(sql)
		db.commit()

	except Exception as e:
		logging.debug("Insertion error to table statistiques_date_temps, details %s" %(e))
		sys.exit()


"""
Function to create initilization matrix without constraint
"""
def create_init_matrix_without_constraint(teamNbr, poolNbr, poolSize):

	try:
		# -------------------------------------- CREATE INIT MATRIX WITHOUT CONSTRAINT --------------------------------#
		# Initialisation matrix P
		P_InitMat = np.zeros((teamNbr, teamNbr))
		
		# determine max and min pool size from normal pool size and variation team number per pool

		# generate a random value for each team
		teamRandomValues = [round(random.random() * 100) for i in range(teamNbr)]
		
		# get the index values of the sorted random values
		indexSortedTeamRandomValues = sorted( range(len(teamRandomValues)), key=lambda k: teamRandomValues[k] )
		
		# attribute pool number to the sorted team values
		teamPoolSorted = []
		for i in range(poolNbr):
			tempList = [i+1]*poolSize
			teamPoolSorted += tempList
		
		# get the pool number of the original (unsorted) team values
		teamPoolResult = [0] * teamNbr
		for i in range(teamNbr):
			teamPoolResult[indexSortedTeamRandomValues[i]] = teamPoolSorted[i]
		
		
		# get index of the teams with the same pool number (create 2D Matrix from list)
		for indexCurPool, curPoolNbr in enumerate(teamPoolResult):
			sameCurValueIndex =  [i for i, x in enumerate(teamPoolResult) if x == curPoolNbr]
			sameCurValueIndex.remove(indexCurPool)
		
			P_InitMat[indexCurPool, sameCurValueIndex] = 1

		return P_InitMat
# 
	except Exception as e:
		show_exception_traceback()


"""
Function to get team name from team Id (with escaped single apostrophe)
"""
def get_team_name_escaped_from_team_id(teamId):
	try:
		teamName = ""
		
		sql = "select nom from entite where id=%s"%teamId
		teamName = db.fetchone(sql).replace("'", u"''")

		return teamName
	except Exception as e:
		show_exception_traceback()

"""
Function to get prohibition constraints
"""
def get_prohibition_constraints(prohibitionDict):
	try:
		# check if the prohibition dictionary is empty or not
		if any(prohibitionDict):
			prohibitionConstraints = {"status": "yes", "data": []} 
			
			for constraintNbr, constraint in prohibitionDict.items():
				# remove white spaces
				team1 = int(constraint[0].strip())
				team2 = int(constraint[1].strip())
				
				prohibitionConstraints["data"].append([team1, team2])
			
		else:
			prohibitionConstraints = {"status": "no", "data": []} 
		

		return prohibitionConstraints
	except Exception as e:
		show_exception_traceback()


"""
Function to get type distribution constraints
"""
def get_type_distribution_constraints(typeDistributionDict):
	try:
		# check if the type distribution dictionary is empty or not
		if any(typeDistributionDict):
# 			typeDistributionConstraints = {"status": "yes", "data": {"espoir":  [7968, 7969]}}
			typeDistributionConstraints = {"status": "yes", "data": {} }
			
			# check type of variable typeDistributionDict
			if isinstance(typeDistributionDict, dict):
				for teamType, members in typeDistributionDict.items():
					typeDistributionConstraints["data"].update({teamType : members})
			elif isinstance(typeDistributionDict, list):
				typeDistributionConstraints["data"].update({"promu" : typeDistributionDict})
				
			else:
				# if typeDistributionDict is not a dict, it is an error
				typeDistributionConstraints = {"status": "no", "data": {}}
				
			
		else:
			typeDistributionConstraints = {"status": "no", "data": {}}

		return typeDistributionConstraints
		
	except Exception as e:
		show_exception_traceback()


"""
Function to check prohibition constraints
Return 1 if failure (any prohibition constraint in the pool distribution)
Return 0 if success (pass the prohibition constraints)
"""
def check_prohibition_constraints(prohibitionConstraints, poolDistribution):
	try:
		for constraint in prohibitionConstraints:
			constraintFirstTeam = int(constraint[0])
			constraintSecondTeam = int(constraint[1])
		
			for pool, poolMembers in poolDistribution.items():
				if constraintFirstTeam in poolMembers and constraintSecondTeam in poolMembers:
					return 1
				
		return 0
		
	except Exception as e:
		show_exception_traceback()
		

"""
Function to check if list 1 is a sublist of list 2 or not
return True if yes
return False if not
"""
def list1_is_sublist_of_list2(list1, list2):
	try:
		for memberList1 in list1:
			if memberList1 not in list2:
				return False

		return True
	except Exception as e:
		show_exception_traceback()
		
"""
Function to distribute team members (type distribution constraint)
"""
def distribute_team_members_type_distribution_constraints(poolNbr, inputConstraintNbr):
	try:
		result = [0] * poolNbr

		for i in range(inputConstraintNbr):
			indexResult = i
				
			while True:	
				if indexResult > len(result)-1:			
					indexResult -= len(result)
				else:
					break
			result[indexResult] += 1

		return result

	except Exception as e:
		show_exception_traceback()

"""
Function to check type distribution constraints
Return 1 if failure (for any distribution type, not all members are in the same pool)
Return 0 if success (all the type distribution constraints are fulfilled)
"""
def check_type_distribution_constraints(typeDistributionConstraints, poolDistribution):
	try:

		# get pool number
		poolNbr = len(poolDistribution.keys())

		for constraintType, constraintTeamMembers in typeDistributionConstraints.items():
			constraintTeamMembersNbr = len(constraintTeamMembers)

			expectedMemberDistribution = distribute_team_members_type_distribution_constraints(poolNbr, constraintTeamMembersNbr)

			currentMemberDistribution = []
			for pool, poolMembers in poolDistribution.items():
				
				# check for each constraintTeamMember
				constraintTeamMembers_inPoolMembersNbr = 0
				for constraintTeamMember in constraintTeamMembers:
					if int(constraintTeamMember) in poolMembers:
						constraintTeamMembers_inPoolMembersNbr += 1
				currentMemberDistribution.append(constraintTeamMembers_inPoolMembersNbr)

			# sort current member distribution
			currentMemberDistribution = sorted(currentMemberDistribution, reverse=True)
			
			# check if current member distribution equals to expected member distribution
			if(currentMemberDistribution != expectedMemberDistribution):
				return 1
				
		return 0
	except Exception as e:
		show_exception_traceback()

"""
Function to create initilization matrix with constraint
"""
def create_init_matrix_with_constraint(teamNbr, poolNbr, poolSize, teams, iterConstraint, prohibitionConstraints, typeDistributionConstraints):

	try:
		# -------------------------------------- CREATE INIT MATRIX WITH CONSTRAINT -------------------------------- #
		for iterNbr in range(iterConstraint):

			# Initialisation matrix P
			P_InitMat = np.zeros((teamNbr, teamNbr))
			
			# generate a random value for each team
			teamRandomValues = [round(random.random() * 100) for i in range(teamNbr)]
			
			# get the index values of the sorted random values
			indexSortedTeamRandomValues = sorted( range(len(teamRandomValues)), key=lambda k: teamRandomValues[k] )
			
			# attribute pool number to the sorted team values
			teamPoolSorted = []
			for i in range(poolNbr):
				tempList = [i+1]*poolSize
				teamPoolSorted += tempList
			
			# get the pool number of the original (unsorted) team values
			teamPoolResult = [0] * teamNbr
			for i in range(teamNbr):
				teamPoolResult[indexSortedTeamRandomValues[i]] = teamPoolSorted[i] 

			# create pool distribution
			poolDistribution = {}
			for i in range(teamNbr):
				team = teams[i]
				pool = teamPoolResult[i]
				
				if pool not in poolDistribution:
					poolDistribution[pool] = [team]
				else:
					poolDistribution[pool].append(team)

			# apply prohibition constraints to the pool distribution
			statusProhibitionConstraints = check_prohibition_constraints(prohibitionConstraints, poolDistribution)

			# apply type distribution constraints to the pool distribution
			statusTypeDistributionConstraints = check_type_distribution_constraints(typeDistributionConstraints, poolDistribution)

			# if the initial P Matrix does not have any problem with the distribution constraints
			if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
				break
		

		# create P Init Matrix only if both status constraints are 0 (success)
		if statusProhibitionConstraints == 0 and statusTypeDistributionConstraints == 0:
			# get index of the teams with the same pool number (create 2D Matrix from list)
			for indexCurPool, curPoolNbr in enumerate(teamPoolResult):
				sameCurValueIndex =  [i for i, x in enumerate(teamPoolResult) if x == curPoolNbr]
				sameCurValueIndex.remove(indexCurPool)
			
				P_InitMat[indexCurPool, sameCurValueIndex] = 1
				

			return {"success": True, "data": P_InitMat}
		else:
			return {"success": False, "data": None}
		# 

	except Exception as e:
		show_exception_traceback()


"""
Function to create initilization matrix with constraint manually
"""
def create_init_matrix_with_constraint_manual(teamNbr, poolNbr, poolSize, teams, iterConstraint, prohibitionConstraints, typeDistributionConstraints):

	try:
		# -------------------------------------- CREATE INIT MATRIX WITH CONSTRAINT MANUALLY -------------------------------- #
		# initialize pool distribution
		poolDistribution = {}
		for pool in range(1, poolNbr+1):
			poolDistribution[pool] = []

		# initialize unassaign teams
		unassignedTeams = list(teams)

		# distribute teams across the pools
		for type, teamsPerType in typeDistributionConstraints.items():
			for indexTeam, teamPerType in enumerate(teamsPerType):
				teamPerType = int(teamPerType)
				assignedPoolNbr = (indexTeam%poolNbr)+1
				
				poolDistribution[assignedPoolNbr].append(teamPerType)
				
				# remmove team from the unassaigned list
				unassignedTeams.remove(teamPerType)

		for prohibition in prohibitionConstraints:
			team1 = int(prohibition[0])
			team2 = int(prohibition[1])
			
			# find pool of team1 and team2
			poolTeam1 = False
			poolTeam2 = False
			
			# find pool of team1 and pool of team2
			for pool, teamsPerPool in poolDistribution.items():
				if team1 in teamsPerPool:
					poolTeam1 = pool
				if team2 in teamsPerPool:
					poolTeam2 = pool
			
			# if team1 and team2 are in the same pool according to type distribution constraints
			if poolTeam1 != False and poolTeam2 != False and poolTeam1 == poolTeam2:
				return {"success": False, "data": None}
			else:
				# try to assign team1
				if not poolTeam1:
					for poolNbrLoop in range(1, poolNbr+1):
						# add to the first pool if it is not full
						if len(poolDistribution[poolNbrLoop]) < poolSize:
							poolDistribution[poolNbrLoop].append(team1)
							unassignedTeams.remove(team1)
							poolTeam1 = poolNbrLoop
							break
						# if all pools are full at the last iteration
						if poolNbrLoop == (poolNbr) and len(poolDistribution[poolNbrLoop]) == poolSize:
							return {"success": False, "data": None}
							
				# try to assign team2
				if not poolTeam2:
					for poolNbrLoop in range(1, poolNbr+1):
						# add to the first pool if it is not full and it is not the same pool of team1
						if len(poolDistribution[poolNbrLoop]) < poolSize and poolNbrLoop != poolTeam1:
							poolDistribution[poolNbrLoop].append(team2)
							unassignedTeams.remove(team2)
							poolTeam2 = poolNbrLoop
							break
						# if all pools are full at the last iteration
						if poolNbrLoop == (poolNbr) and len(poolDistribution[poolNbrLoop]) == poolSize:
							return {"success": False, "data": None}

		# try to distribute the remaining teams
		for unassignedTeam in unassignedTeams:
			for poolNbrLoop in range(1, poolNbr+1):
				# add to the first pool if it is not full
				if len(poolDistribution[poolNbrLoop]) < poolSize:
					poolDistribution[poolNbrLoop].append(unassignedTeam)
					break
				# if all pools are full at the last iteration
				if poolNbrLoop == (poolNbr) and len(poolDistribution[poolNbrLoop]) == poolSize:
					return {"success": False, "data": None}
			

		P_Mat = create_matrix_from_pool_distribution(poolDistribution, teamNbr, teams)

		return {"success": True, "data": P_Mat}

	except Exception as e:
		show_exception_traceback()



"""
Function to create phantom distance matrix from distance matrix
"""
def create_phantom_distance_matrix(D_Mat, teamNbr, poolNbr, poolSize):
	try:

		D_Mat_phantom = np.zeros((poolNbr*poolSize, poolNbr*poolSize))

		for row in range(teamNbr):
			for col in range(teamNbr):
				D_Mat_phantom[row][col] = D_Mat[row][col]

		return D_Mat_phantom

	except Exception as e:
		show_exception_traceback()

"""
Function to create P Matrix from pool distribution
"""
def create_matrix_from_pool_distribution(poolDistribution, teamNbr, teams):
	try:
		# Initialisation matrix P
		P_Mat = np.zeros((teamNbr, teamNbr))
		
		# create pool distribution using indexes
		indexesPoolDistribution = {}
		
		for pool, teamMembers in poolDistribution.items():
			indexesPoolDistribution[pool] = []
			
			for member in teamMembers:
				index = teams.index(member)
				indexesPoolDistribution[pool].append(index)
				
		
		# fill in P_Mat
		for pool, indexesTeamMembers in indexesPoolDistribution.items():
			for indexFirstMember in indexesTeamMembers:
				indexesOtherMembers =  list(indexesTeamMembers)
				indexesOtherMembers.remove(indexFirstMember)
				for indexSecondMember in indexesOtherMembers:
					P_Mat[indexFirstMember][indexSecondMember] = 1
		
		return P_Mat
	except Exception as e:
		show_exception_traceback()

"""
Function to get coordinates points of pool distribution
"""
def	get_coords_pool_distribution(poolDistribution):
	try:
		poolDistributionCoords = {}
		
		for pool, members in poolDistribution.items():
			poolDistributionCoords[pool] = []
			
			for member in members:
				sql = "select latitude, longitude from entite where id=%s"%member
				lat, lon = db.fetchone_multi(sql)
				poolDistributionCoords[pool].append((lat, lon))
			
		return poolDistributionCoords
	except Exception as e:
		show_exception_traceback()
		
"""
Function to get list of names and cities from list of entity ids
"""		
def get_list_details_from_list_ids_for_entity(listIds):
	try:
		listDetails = {"ids":[], "names": [], "cities": []}
		
		sql = "select id from entite where id in (%s)"%(listIds)
		listDetails["ids"] = db.fetchone_column(sql)

		sql = "select nom from entite where id in (%s)"%(listIds)
		listDetails["names"] = db.fetchone_column(sql)

		sql = "select ville from entite where id in (%s)"%(listIds)
		listDetails["cities"] = db.fetchone_column(sql)
		
		return listDetails
		
	except Exception as e:
		show_exception_traceback()
	
	

"""
Function to make variation of team number per pool
"""
def variation_team_number_per_pool(poolsIds, varTeamNbrPerPool):
	try:

		poolNbr = len(poolsIds.keys())

		poolsIdsCopy = dict.copy(poolsIds)
		resultPoolsIds = {}

		# if pool number is even
		if poolNbr % 2 == 0:
			
			tmpTeams = []
			for index, (pool, teams) in enumerate(poolsIdsCopy.items(), start=1):
			
				# remove teams from odd number pool
				if index % 2 == 1:
					for i in range(varTeamNbrPerPool):
						tmpTeams.append(teams.pop())
				
				# add teams to even number pool
				if index % 2 == 0:
					teams += tmpTeams
					tmpTeams = []
				resultPoolsIds[pool] = teams

		# if pool number is odd
		if poolNbr % 2 == 1:
		
			tmpTeams = []
			for index, (pool, teams) in enumerate(poolsIdsCopy.items(), start=1):
				# ignore last pool
				if index != poolNbr:

					# remove teams from odd number pool
					if index % 2 == 1:
						for i in range(varTeamNbrPerPool):
							tmpTeams.append(teams.pop())
					
					# add teams to even number pool
					if index % 2 == 0:
						teams += tmpTeams
						tmpTeams = []
					resultPoolsIds[pool] = teams
				
		return resultPoolsIds
	except Exception as e:
		show_exception_traceback()

	

"""
Function to get report name from report id
"""
def get_report_name_from_report_id(reportId):
	try:
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)

		return reportName

	except Exception as e:
		show_exception_traceback(reportId)

"""
Function to get user email from user id
"""
def get_user_email_from_user_id(userId):
	try:
		# get user's email from user id
		sql = "select email from fos_user where id=%s"%userId
		email = db.fetchone(sql)

		return email

	except Exception as e:
		show_exception_traceback()

"""
Function to send email to user concerning the job finished status
"""
def send_email_to_user(userId, resultId):
	try:
		
		recipientAddress = get_user_email_from_user_id(userId)

		senderAccount = config.EMAIL.From

		url="%s/admin/poules/resultat/%s"%(config.INPUT.MainUrl, resultId)
		subject = u'OPTIMOUV - mise à disposition de vos résultats de calculs'
		contentText = u"Bonjour,\n\n" 
		contentText += u"Le résultat de votre calcul est disponible.\n"
		contentText += u"Vous pouvez le consulter en cliquant sur ce lien :\n" 
		contentText += u"%s\n\n"%(url)
		contentText += u"Cordialement,\n\n"
		contentText += u"L'équipe d’Optimouv\n"
		contentText += u"%s"%(senderAccount)
		
		send_email_general(recipientAddress, subject, contentText)
		

	except Exception as e:
		show_exception_traceback()

"""
General Function to send email 
"""
def send_email_general(recipientAddress, subject, contentText ):
	try:
		# Gmail Sign In
		loginAccount = config.EMAIL.Account
		senderPassword = config.EMAIL.Password
		
		server = smtplib.SMTP(config.EMAIL.Server, config.EMAIL.Port)
		server.ehlo()
		server.starttls()
		server.login(loginAccount, senderPassword)
		
		senderAccount = config.EMAIL.From
		
		msg = MIMEText(contentText)
		# include name and address email at the same time
		msg['From'] = formataddr((str(Header(senderAccount, 'utf-8')), senderAccount))
		msg['To'] = recipientAddress
		msg['Subject'] = subject
				
		server.sendmail(senderAccount, recipientAddress, msg.as_string())
		server.quit()	
	
	except Exception as e:
		show_exception_traceback()

"""
Function to send email to user when there is no results (there are too many constraints)
"""
def send_email_to_user_failure(userId, reportId, channel, method):
	try:

		reportName = get_report_name_from_report_id(reportId)
		senderAccount = config.EMAIL.From

		contentText = u"Bonjour,\n\n" 
		contentText += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
		contentText += u"Veuillez modifier vos critères et contraintes puis relancer un calcul. \n\n" 
		contentText += u"Cordialement,\n\n"
		contentText += u"L'équipe d’Optimouv\n"
		contentText += u"%s"%(senderAccount)

		send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)
		
	except Exception as e:
		show_exception_traceback(reportId)


"""
Function to send email to user when the provided params are unexpected (for match plateau)
"""
def send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method):
	try:
		recipientAddress = get_user_email_from_user_id(userId)

		subject = u'OPTIMOUV - mise à disposition de vos résultats de calculs'
		
		send_email_general(recipientAddress, subject, contentText)

		# update job status
		update_job_status(reportId, -1)

		# ack message
		channel.basic_ack(delivery_tag = method.delivery_tag)


		sys.exit()

	except Exception as e:
		show_exception_traceback(reportId)



"""
Function control provided params by user
"""
def control_params_match_plateau(userId, teamNbr, poolNbr, reportId, channel, method):
	try:
		reportName = get_report_name_from_report_id(reportId)
		poolSize = int(teamNbr/poolNbr)
		senderAccount = config.EMAIL.From

		contentText = u"Bonjour,\n\n" 
		contentText += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName

		# team number has to be the multiplication of 9 (9, 18, 27)
		if teamNbr % 9 != 0:
			contentText += u"Veuillez vous assurer que le nombre de ligne dans votre fichier correspond bien à des rencontres en match plateau.\n\n" 

		# pool number has to be 9
		elif poolSize != 9:
			contentText += u"Veuillez vous assurer que le nombre de poule sélectionné correspond au nombre de ligne dans votre fichier.\n\n" 

		if teamNbr % 9 != 0 or poolSize != 9:
			contentText += u"Cordialement,\n\n"
			contentText += u"L'équipe d’Optimouv\n"
			contentText += u"%s"%(senderAccount)

			send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)


	except Exception as e:
		show_exception_traceback(reportId)



"""
Function to save result into DB
"""
def save_result_to_db(launchType, reportId, groupId, results):
	try:
		resultId = -1
		
		
		if "params" in results:
			# characters substitution for prohibition constraints
			if "interdictions" in results["params"]:
				for indexProhibition, contentProhibition in results["params"]["interdictions"].items():
					for indexName, name in enumerate(contentProhibition["noms"]):
						results["params"]["interdictions"][indexProhibition]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentProhibition["villes"]):
						results["params"]["interdictions"][indexProhibition]["villes"][indexCity] = city.replace("'", u"''")

		
			# characters substitution for type distribution constraints
			if "repartitionsHomogenes" in results["params"]:
				for teamType, contentTypeDistribution in results["params"]["repartitionsHomogenes"].items():
					for indexName, name in enumerate(contentTypeDistribution["noms"]):
						results["params"]["repartitionsHomogenes"][teamType]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentTypeDistribution["villes"]):
						results["params"]["repartitionsHomogenes"][teamType]["villes"][indexCity] = city.replace("'", u"''") 
		
		name = "%s_rapport_%s_groupe_%s"%(launchType , reportId, groupId) 
		km = 0
		travelTime = 0
		creationDate = time.strftime("%Y-%m-%d")
		modificationDate = time.strftime("%Y-%m-%d")
		co2Car = 0
		co2SharedCar = 0
		co2Bus = 0
		costCar = 0
		costSharedCar = 0
		costBus = 0
		
		try:
			sql = """insert into resultats (id_rapport, nom, kilometres, duree, date_creation, date_modification, 
						co2_voiture, co2_covoiturage, co2_minibus, cout_voiture, cout_covoiturage, cout_minibus, details_calcul ) 
				values ( %(reportId)s , '%(name)s', %(km)s, %(travelTime)s,' %(creationDate)s', '%(modificationDate)s',
						%(co2Car)s, %(co2SharedCar)s, %(co2Bus)s, %(costCar)s, %(costSharedCar)s, %(costBus)s, '%(results)s' )
				"""%{	"reportId": reportId, 
						"name": name,
						"km": km,
						"travelTime": travelTime,
						"creationDate": creationDate,
						"modificationDate": modificationDate,
						"co2Car": co2Car,
						"co2SharedCar": co2SharedCar,
						"co2Bus": co2Bus,
						"costCar": costCar,
						"costSharedCar": costSharedCar,
						"costBus": costBus,
						"results": json.dumps(results),
						
					}
			db.execute(sql)
			db.commit()
			
			resultId = db.lastinsertedid()
		except Exception as e:
			logging.error("Insertion error to table resultats, details %s" %(e))
			sys.exit()

		return resultId
	except Exception as e:
		show_exception_traceback(reportId)


"""
Function to save result into DB
"""
def save_result_to_db_post_treatment(launchType, reportId, groupId, results):
	try:
		resultId = -1
		
		if "params" in results:
			# characters substitution for prohibition constraints
			if "interdictions" in results["params"]:
				for indexProhibition, contentProhibition in results["params"]["interdictions"].items():
					for indexName, name in enumerate(contentProhibition["noms"]):
						results["params"]["interdictions"][indexProhibition]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentProhibition["villes"]):
						results["params"]["interdictions"][indexProhibition]["villes"][indexCity] = city.replace("'", u"''")

			# characters substitution for type distribution constraints
			if "repartitionsHomogenes" in results["params"]:
				for teamType, contentTypeDistribution in results["params"]["repartitionsHomogenes"].items():
					for indexName, name in enumerate(contentTypeDistribution["noms"]):
						results["params"]["repartitionsHomogenes"][teamType]["noms"][indexName] = name.replace("'", u"''")
					for indexCity, city in enumerate(contentTypeDistribution["villes"]):
						results["params"]["repartitionsHomogenes"][teamType]["villes"][indexCity] = city.replace("'", u"''") 
		
		# escape single apostrophe for city names
		# ref scenario
		resultsRef = results["scenarioRef"]
		if resultsRef:
			replace_single_quote_for_result(resultsRef["rencontreDetails"])

		# optimal scenario
		resultsOptimalWithoutConstraint = results["scenarioOptimalSansContrainte"]
		if resultsOptimalWithoutConstraint:
			replace_single_quote_for_result(resultsOptimalWithoutConstraint["rencontreDetails"])
		
		resultsOptimalWithConstraint = results["scenarioOptimalAvecContrainte"]
		if resultsOptimalWithConstraint:
			replace_single_quote_for_result(resultsOptimalWithConstraint["rencontreDetails"])
			
		# equitable scenario
		resultsEquitableWithoutConstraint = results["scenarioEquitableSansContrainte"]
		if resultsEquitableWithoutConstraint:
			replace_single_quote_for_result(resultsEquitableWithoutConstraint["rencontreDetails"])
			
		
		resultsEquitableWithConstraint = results["scenarioEquitableAvecContrainte"]
		if resultsEquitableWithConstraint:
			replace_single_quote_for_result(resultsEquitableWithConstraint["rencontreDetails"])

		name = "%s_rapport_%s_groupe_%s"%(launchType , reportId, groupId) 
		km = 0
		travelTime = 0
		creationDate = time.strftime("%Y-%m-%d")
		modificationDate = time.strftime("%Y-%m-%d")
		co2Car = 0
		co2SharedCar = 0
		co2Bus = 0
		costCar = 0
		costSharedCar = 0
		costBus = 0
		
		try:
			sql = """insert into resultats (id_rapport, nom, kilometres, duree, date_creation, date_modification, 
						co2_voiture, co2_covoiturage, co2_minibus, cout_voiture, cout_covoiturage, cout_minibus, details_calcul ) 
				values ( %(reportId)s , '%(name)s', %(km)s, %(travelTime)s, '%(creationDate)s', '%(modificationDate)s',
	 					%(co2Car)s, %(co2SharedCar)s, %(co2Bus)s, %(costCar)s, %(costSharedCar)s, %(costBus)s, '%(results)s' )
				"""%{	"reportId": reportId, 
						"name": name,
						"km": km,
						"travelTime": travelTime,
						"creationDate": creationDate,
						"modificationDate": modificationDate,
						"co2Car": co2Car,
						"co2SharedCar": co2SharedCar,
						"co2Bus": co2Bus,
						"costCar": costCar,
						"costSharedCar": costSharedCar,
						"costBus": costBus,
						"results": json.dumps(results),
						
					}
			db.execute(sql)
			db.commit()
			
			resultId = db.lastinsertedid()
		except Exception as e:
			logging.error("Insertion error to table resultats, details %s" %(e))
			sys.exit()
		
		return resultId
	except Exception as e:
		show_exception_traceback(reportId)



"""
Function to get team names from their ids
"""
def get_team_names_from_ids(teamIds):
	try:
		teamNames = []
		
		for teamId in teamIds:
			sql = "select nom from entite where id=%s"%(teamId)
			teamName = db.fetchone(sql)
			teamNames.append(teamName)
	
		return teamNames
	
	except Exception as e:
		show_exception_traceback()

"""
Function to calculate distance from encounters details for match plateau
"""
def calculate_distance_from_encounters_plateau(detailsPlateau):
	try:
		distance = 0

		for pool, contentPool in detailsPlateau.items():
			for day, contentDay in contentPool.items():
				for contentGroup in contentDay:
					distance += contentGroup["distanceGroupeTousParticipants"]

		return distance
	
	except Exception as e:
		show_exception_traceback()

"""
Function to get list of encounters from host team and other teams (for match plateau)
"""
def get_list_encounters_plateau(hostTeamId, hostTeamName, otherTeamsIds, otherTeamNames):
	try:
		results = {	"groupDistance": 0,
					"groupDistanceAllParticipants": 0,
					"groupTravelTime": 0,
					"travelIds": [], 
					"travelNames":  [], 
					"participantsNbr": 0
				}
		
		# encounter ids
		for otherTeamId in otherTeamsIds:
			travelId = [hostTeamId, otherTeamId]
			results["travelIds"].append(travelId)
			
		# encounter names
		for otherTeamName in otherTeamNames:
			travelName = [hostTeamName, otherTeamName]
			results["travelNames"].append(travelName)
		
		# distance
		for travelId in results["travelIds"]:
			cityTo = travelId[0]
			cityFrom = travelId[1]
			
			sql = "select distance, duree from trajet where depart=%s and destination=%s"%(cityFrom, cityTo)
			distance, travelTime = db.fetchone_multi(sql)
			
			results["groupDistance"] += distance
			results["groupTravelTime"] += travelTime

			sql = "select participants from entite where id=%s"%cityFrom
			participantsNbr = db.fetchone(sql)
			
			results["participantsNbr"] += participantsNbr

			results["groupDistanceAllParticipants"] += (participantsNbr * distance)

		# divide participants number according to number of travel Id
		results["participantsNbr"] = int( results["participantsNbr"]/ len(results["travelIds"]))
		

		return results
		
	except Exception as e:
		show_exception_traceback()



"""
Function to get reference scenario for match plateau
"""
def get_ref_scenario_plateau(teamsIds, userId, reportId, channel, method):
	try:
		refScenario = {"status" : "no", "data": {} }
		
		teamNames = get_team_names_from_ids(teamsIds)
		
		listChars = []
		
		for team in teamsIds:
			sql = "select id, nom, ville, code_postal,  poule, ref_plateau from entite where id=%s"%team
			hostTeamId, hostTeamName, hostTeamCity, hostTeamPostalCode, poolId, refPlateau = db.fetchone_multi(sql)

			# check if there is any reference for match plateau
			if refPlateau is None:
				sql = "select nom from parametres where id=%s"%reportId
				reportName = db.fetchone(sql)

				senderAccount = config.EMAIL.From
				contentText = u"Bonjour,\n\n" 
				contentText += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
				contentText += u"Veuillez vérifier qu'il existe les données de référence pour le match plateau dans le fichier csv utilisé.\n\n" 
				contentText += u"Cordialement,\n\n"
				contentText += u"L'équipe d’Optimouv\n"
				contentText += u"%s"%(senderAccount)
				send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)


			# escape single quote			
			hostTeamName = hostTeamName.replace("'", u"''")
			hostTeamCity = hostTeamCity.replace("'", u"''")
			
			refPlateau = json.loads(refPlateau)

			firstDay = int(refPlateau["premierJourReception"])
			# continue to next value if value of firstDay is zero
			if firstDay == 0 or firstDay == "0":
				continue

			firstDayFirstTeamName = refPlateau["premierJourEquipe1"]
			firstDaySecondTeamName = refPlateau["premierJourEquipe2"]

			firstDayFirstTeamId = teamsIds[teamNames.index(firstDayFirstTeamName)]
			sql = "select ville, code_postal from entite where id=%s"%firstDayFirstTeamId
			firstDayFirstTeamCity, firstDayFirstTeamPostalCode = db.fetchone_multi(sql)
			
			firstDaySecondTeamId = teamsIds[teamNames.index(firstDaySecondTeamName)]
			sql = "select ville, code_postal from entite where id=%s"%firstDaySecondTeamId
			firstDaySecondTeamCity, firstDaySecondTeamPostalCode = db.fetchone_multi(sql)

			# escape single quote			
			firstDayFirstTeamName = firstDayFirstTeamName.replace("'", u"''")
			firstDaySecondTeamName = firstDaySecondTeamName.replace("'", u"''")
			firstDayFirstTeamCity = firstDayFirstTeamCity.replace("'", u"''")
			firstDaySecondTeamCity = firstDaySecondTeamCity.replace("'", u"''")

			listEncountersGroup = get_list_encounters_plateau(hostTeamId, hostTeamName, [firstDayFirstTeamId, firstDaySecondTeamId] , [firstDayFirstTeamName, firstDaySecondTeamName] )

			contentTmp = {	"hoteId": hostTeamId, 
							"hoteNom": hostTeamName,
							"hoteVille": hostTeamCity,
							"hoteCodePostal": hostTeamPostalCode,

							"premierEquipeId": firstDayFirstTeamId, 
							"premierEquipeNom" : firstDayFirstTeamName, 
							"premierEquipeVille" : firstDayFirstTeamCity, 
							"premierEquipeCodePostal" : firstDayFirstTeamPostalCode, 

							"deuxiemeEquipeId": firstDaySecondTeamId , 
							"deuxiemeEquipeNom": firstDaySecondTeamName, 
							"deuxiemeEquipeVille": firstDaySecondTeamCity, 
							"deuxiemeEquipeCodePostal": firstDaySecondTeamPostalCode, 
							
							"distanceGroupe": listEncountersGroup["groupDistance"], 
							"distanceGroupeTousParticipants": listEncountersGroup["groupDistanceAllParticipants"], 
							"dureeGroupe": listEncountersGroup["groupTravelTime"],
							"deplacementsIds": listEncountersGroup["travelIds"], 
							"deplacementsNoms": listEncountersGroup["travelNames"],
							"nbrParticipants": listEncountersGroup["participantsNbr"]
							}
			refScenario["status"] = "yes"

			secondDay = int(refPlateau["deuxiemeJourReception"])

			# continue to next value if value of firstDay is zero
			if secondDay != 0 and secondDay != "0":
				secondDayFirstTeamName = refPlateau["deuxiemeJourEquipe1"]
				secondDaySecondTeamName = refPlateau["deuxiemeJourEquipe2"]

				secondDayFirstTeamId = teamsIds[teamNames.index(secondDayFirstTeamName)]
				sql = "select ville, code_postal from entite where id=%s"%secondDayFirstTeamId
				secondDayFirstTeamCity, secondDayFirstTeamPostalCode = db.fetchone_multi(sql)

				secondDaySecondTeamId = teamsIds[teamNames.index(secondDaySecondTeamName)]
				sql = "select ville, code_postal from entite where id=%s"%secondDaySecondTeamId
				secondDaySecondTeamCity, secondDaySecondTeamPostalCode = db.fetchone_multi(sql)
			
				# escape single quote			
				secondDayFirstTeamName = secondDayFirstTeamName.replace("'", u"''")
				secondDaySecondTeamName = secondDaySecondTeamName.replace("'", u"''")
				secondDayFirstTeamCity = secondDayFirstTeamCity.replace("'", u"''")
				secondDaySecondTeamCity = secondDaySecondTeamCity.replace("'", u"''")

			#############################################################################################################
			# Patch for front, convert from pool letter given by users to number # FIXME !!!!
			if poolId not in listChars:
				listChars.append(poolId)
			poolId = (listChars.index(poolId)) + 1
			#############################################################################################################

			# pool not yet in reference dict
			if poolId not in refScenario["data"]:
				refScenario["data"][poolId] = {}
				refScenario["data"][poolId][firstDay] = [contentTmp]

				if secondDay == 0 or secondDay == "0":
					continue
				
				listEncountersGroup = get_list_encounters_plateau(hostTeamId, hostTeamName, [secondDayFirstTeamId, secondDaySecondTeamId] , [secondDayFirstTeamName, secondDaySecondTeamName] )

				contentTmp = {	"hoteId": hostTeamId, 
								"hoteNom": hostTeamName, 
								"hoteVille": hostTeamCity,
								"hoteCodePostal": hostTeamPostalCode,

								"premierEquipeId": secondDayFirstTeamId, 
								"premierEquipeNom" : secondDayFirstTeamName, 
								"premierEquipeVille" : secondDayFirstTeamCity, 
								"premierEquipeCodePostal" : secondDayFirstTeamPostalCode, 

								"deuxiemeEquipeId": secondDaySecondTeamId , 
								"deuxiemeEquipeNom": secondDaySecondTeamName, 
								"deuxiemeEquipeVille": secondDaySecondTeamCity, 
								"deuxiemeEquipeCodePostal": secondDaySecondTeamPostalCode, 

								"distanceGroupe": listEncountersGroup["groupDistance"], 
								"distanceGroupeTousParticipants": listEncountersGroup["groupDistanceAllParticipants"], 
								"dureeGroupe": listEncountersGroup["groupTravelTime"],
								"deplacementsIds": listEncountersGroup["travelIds"], 
								"deplacementsNoms": listEncountersGroup["travelNames"],
								"nbrParticipants": listEncountersGroup["participantsNbr"]
								}
				refScenario["data"][poolId][secondDay] = [contentTmp]
			# pool already in reference dict
			else:
				# first day reference
				if firstDay in refScenario["data"][poolId]:
					refScenario["data"][poolId][firstDay].append(contentTmp)
				else:
					refScenario["data"][poolId][firstDay] = [contentTmp]
					
				if secondDay == 0 or secondDay == "0":
					continue

				# second day reference
				if secondDay in refScenario["data"][poolId]:
					refScenario["data"][poolId][secondDay].append(contentTmp)
				else:
					refScenario["data"][poolId][secondDay] = [contentTmp]
					
		return refScenario
	
	except Exception as e:
		show_exception_traceback(reportId)
		


"""
Function to escape single quote
"""
def replace_single_quote_for_result(result):
	try:
		for pool, contentPool in result.items():
			for encounterNbr, contentEncounter in contentPool.items():
				contentEncounter["equipeDepartNom"] = contentEncounter["equipeDepartNom"].replace("'", u"''")
				contentEncounter["equipeDestinationNom"] = contentEncounter["equipeDestinationNom"].replace("'", u"''")
				contentEncounter["equipeDepartVille"] = contentEncounter["equipeDepartVille"].replace("'", u"''")
				contentEncounter["equipeDestinationVille"] = contentEncounter["equipeDestinationVille"].replace("'", u"''")

	except Exception as e:
		show_exception_traceback()

"""
Function to check final result
send error message to user if one tries to relaunch based on final result
final result flag is set when user tries to play the variation of team number in pools
"""
def check_final_result(calculatedResult, userId, reportId, channel, method):
	try:
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)
		senderAccount = config.EMAIL.From

		if "params" in calculatedResult:
			if "final" in calculatedResult["params"]:
				if results["params"]["final"] == "oui":
					
					contentText = u"Bonjour,\n\n" 
					contentText += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
					contentText += u"Vous ne pouvez lancer le critère de variation du nombre d'équipes par poule qu'une seule fois.\n\n" 
					contentText += u"Cordialement,\n\n"
					contentText += u"L'équipe d’Optimouv\n"
					contentText += u"%s"%(senderAccount)
					send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)
					
	except Exception as e:
		show_exception_traceback(reportId)

"""
Function to check params for post treatment (variation of team members per pool) round trip and one way match
"""
def check_given_params_post_treatment(calculatedResult, launchType, poolNbr, prohibitionConstraints, typeDistributionConstraints, userId, reportId, channel, method):
	try:
		errorStatus = False
		
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)
		
		if ( calculatedResult["typeMatch"] != launchType) or (int(calculatedResult["nombrePoule"]) != int(poolNbr)):
			errorStatus = True
		
		# map list of strings to list of ints for prohibition constraints
		prohibitionConstraintsInput = [sorted(list(map(int, prohibitionConstraint))) for prohibitionConstraint in prohibitionConstraints]

		# map list of strings to list of ints for type distribution constraints
		typeDistributionConstraintsInput = {}
		for type, members in typeDistributionConstraints.items(): 
			typeDistributionConstraintsInput[type] = sorted(list(map(int, members)))

		if "params" in calculatedResult:
			# check prohibition constraints
			prohibitionConstraintsSaved = []
			if "interdictions" in calculatedResult["params"]:
				prohibitionConstraintsSavedUnformatted = calculatedResult["params"]["interdictions"]
				for prohibitionNbr, prohibitionConstraintSavedUnformatted in prohibitionConstraintsSavedUnformatted.items():
					prohibitionConstraintsSaved.append(sorted(prohibitionConstraintSavedUnformatted["ids"]))
			if prohibitionConstraintsInput != prohibitionConstraintsSaved: 
				errorStatus = True
			# check type distribution constraints
			typeDistributionConstraintsSaved = {}
			if "repartitionsHomogenes" in calculatedResult["params"]:
				typeDistributionConstraintsSavedUnformatted = calculatedResult["params"]["repartitionsHomogenes"]
				for type, typeDistributionConstraintSavedUnformatted in typeDistributionConstraintsSavedUnformatted.items():
					typeDistributionConstraintsSaved[type] = sorted(typeDistributionConstraintSavedUnformatted["ids"])
			if typeDistributionConstraintsInput != typeDistributionConstraintsSaved: 
				errorStatus = True

		# send email if errorStatus is true
		senderAccount = config.EMAIL.From
		if errorStatus:
			contentText = u"Bonjour,\n\n" 
			contentText += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
			contentText += u"Veuillez utiliser les mêmes paramètres que vous avez utilisé précédemment.\n\n" 
			contentText += u"Cordialement,\n\n"
			contentText += u"L'équipe d’Optimouv\n"
			contentText += u"%s"%(senderAccount)
			send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)
		
	except Exception as e:
		show_exception_traceback(reportId)


"""
Function to check request validity (post treatment can only launch variation of team number per pool or team transfer to other pool)
"""
def check_request_validity_post_treatment(teamTransfers, varTeamNbrPerPool, userId, reportId, channel, method):
	try:		
		sql = "select nom from parametres where id=%s"%reportId
		reportName = db.fetchone(sql)

		senderAccount = config.EMAIL.From
		if int(varTeamNbrPerPool)> 1 and teamTransfers:
			contentText = u"Bonjour,\n\n" 
			contentText += u"Aucun résultat n'est disponible pour votre rapport : %s. \n" %reportName
			contentText += u"Veuillez choisir soit la variation du nombre d'équipes par poule soit le changement d'affectation d'équipes par poule.\n\n" 
			contentText += u"Cordialement,\n\n"
			contentText += u"L'équipe d’Optimouv\n"
			contentText += u"%s"%(senderAccount)
			send_email_to_user_failure_with_text(userId, reportId, contentText, channel, method)

	except Exception as e:
		show_exception_traceback(reportId)



"""
Function to update job status
"""
def update_job_status(jobId, status):
	try:
		sql = "update parametres set statut=%(status)s where id=%(jobId)s"%{"status": int(status), "jobId": int(jobId)}
		db.execute(sql)
		db.commit()

	except Exception as e:
		show_exception_traceback()

