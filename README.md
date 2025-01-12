# HAWKI - fragen-playground

## About

fragen-playground ist ein Branch von HAWKI, um das feature "upload private files und generate question about file with the help of a llm" zu implementieren.

## Lösungstrategie
1. Der Datei Upload und die benötigte Oberfläche SOLL mittels PHP im Rahmenprogramm von HAWKI umgesetzt werden.  
2. Die hochgeladene "Datei" kann einem Python Servive (siehe unten) übergeben werden zur weiteren Bearbeitung innerhalb der Benutzer Session.  
3. Der Python Service "indiziert" den Text der PDF Datei und legt den "Index" (oder die entsprechenden Chunks) für die Session im Speicher ab. Später können wir auch OCR der Images der PDF Datei machen und die Chunks in einer (persistenten) Vektordatenbank abspeichern. Zunächst wird die Indizierung mit Standard Kompoenten des Frameworks lang-chain gemacht (und mit der in Memory Vektordatenbnak chromaDb).  
4. Der Service gibt dem Aufrufer eine ID zurück. Diese ID muss der Aufrufer mitgeben, wenn er "Fragen" stellen will. Die Abfrage des LLM mit den extrahierten Chunks erfolgt ebenfalls mit lang-chain in dem Python Service  

### Offene Fragen 
1. Kann der Python Service vom Betreiber von HAWKI betrieben werden? Kann ein entsprechendes Python Environment bereit gestellt werden?
2. Welche Größe von PDF Dateien ist zugelassen? Welche Sicherheitsmaßnahmen müssen beim Upload beachet werden?
3. Integration der oben erwähnten Weiterentwicklung in HAWKI
4. Deployment Verfahren

## Proof of Concept
- :white_check_mark: Installation HAWKI auf Entwicklungssystem <https://hawki.vorgehen.de> 
- :white_check_mark: Python Jupyter Notebook, um mittels lang-chain ein PDF in In Memory Vektor-DB zu bringen
- :heavy_plus_sign: Entwickeln eines Python Services für die Abfrage von LLM unter Anreicherung von Chunks
- :heavy_plus_sign: Dateiupload und rudimentäre Prompt Oberfläche
- :heavy_plus_sign: Integration: Übermittlung der Aufrufe von der Oberfläche an den Python Service


## Contact & License

This project is licensed under the terms of the MIT license.
