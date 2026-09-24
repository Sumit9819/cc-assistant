"""Build exact, reviewable edits from the fresh site snapshots. Does not submit."""
import copy,json,re,sys,html as htmlmod
from pathlib import Path
from inspect import walk
root=Path(__file__).resolve().parent
evidence=Path('D:/cc-assistant/reports/site-fixes-2026-09-09')
posts={}
for f in [evidence/'initial.json',*evidence.glob('reads-*-results.json'),*evidence.glob('author-reads-results.json')]:
    for r in json.loads(f.read_text(encoding='utf-8')):
        if r['name']=='get_post' and not r.get('isError'):posts[r['result']['id']]=r['result']
nodes={pid:{n['id']:n for n in walk(p)} for pid,p in posts.items()}
updates={}; reasons={}; extras=[]; change_log=[]
def setting(pid,wid,key):
    assert wid in nodes[pid],(pid,wid)
    return copy.deepcopy(updates.get(pid,{}).get(wid,{}).get(key,nodes[pid][wid]['settings'].get(key)))
def put(pid,wid,reason,**values):
    assert wid in nodes[pid],(pid,wid)
    for k,v in values.items():
        before=setting(pid,wid,k)
        if before==v:continue
        updates.setdefault(pid,{}).setdefault(wid,{})[k]=v
        change_log.append({'post_id':pid,'widget_id':wid,'field':k,'before':before,'after':v,'reason':reason})
        if reason not in reasons.setdefault(pid,[]):reasons[pid].append(reason)
def replace(pid,wid,key,old,new,reason):
    val=setting(pid,wid,key);assert isinstance(val,str) and old in val,(pid,wid,old)
    put(pid,wid,reason,**{key:val.replace(old,new)})
def core(pid,field,value,reason):
    extras.append({'name':'draft_update_post_meta','arguments':{'post_id':pid,'field':field,'value':str(value),'summary':reason,'reasoning':'September 9 live audit correction. '+reason}})
def seo(pid,key,value,reason):
    extras.append({'name':'draft_update_seo_meta','arguments':{'post_id':pid,'logical_key':key,'value':value,'summary':reason,'reasoning':'September 9 live audit: '+reason}})
def paragraphs(s):return ''.join(re.findall(r'<p\b[^>]*>.*?</p>',s,re.S))
site='https://erofwhiterock.com'
cdc='https://www.cdc.gov/stroke/signs-symptoms/index.html'
cdc_es='https://www.cdc.gov/stroke/es/signs-symptoms/signos-y-sintomas-de-accidente-cerebrovascular.html'
nih='https://www.nhlbi.nih.gov/health/stroke/treatment'

# Correct both language versions and their manually embedded MedicalWebPage JSON-LD.
for pid,es in [(2482,False),(4739,True)]:
    why='WR-01: correct stroke guidance and schema using CDC/NIH; remove unverified guarantees and rankings.'
    put(pid,'d7f4d6f',why,editor=('<p>Si sospecha un accidente cerebrovascular, llame al 911 para pedir una ambulancia. No conduzca ni espere a que los síntomas desaparezcan.</p><p>ER of White Rock ofrece evaluación de emergencia, tomografía computarizada y estabilización inicial las 24 horas. Cuando se necesita atención especializada, coordinamos el traslado al centro adecuado.</p>' if es else '<p>If you suspect a stroke, call 911 for an ambulance. Do not drive or wait for symptoms to pass.</p><p>ER of White Rock provides emergency evaluation, CT imaging and initial stabilization 24 hours a day. When specialist care is needed, we coordinate transfer to an appropriate facility.</p>'))
    put(pid,'7a40c42e',why,title=('¿Sospecha un derrame cerebral? Llame al 911' if es else 'Suspected Stroke? Call 911'),link={**setting(pid,'7a40c42e','link'),'url':'tel:911'})
    put(pid,'261e9fcc',why,title=('Señales de un accidente cerebrovascular: actúe de inmediato' if es else 'Stroke Warning Signs: Act Immediately'))
    intro=('<p>La pérdida repentina del equilibrio o de la visión, la caída de un lado de la cara, la debilidad en un brazo y la dificultad para hablar pueden ser señales de un accidente cerebrovascular. Un dolor de cabeza repentino e intenso también requiere atención inmediata. <a href="'+cdc_es+'">Los CDC recomiendan llamar al 911</a>, incluso si los síntomas desaparecen.</p>' if es else '<p>Sudden loss of balance or vision, face drooping, arm weakness and speech trouble can signal a stroke. A sudden severe headache is another warning sign. <a href="'+cdc+'">CDC guidance recommends calling 911</a>, even if the symptoms go away.</p>')
    treatment=('<p>El tratamiento depende de si un coágulo bloquea el flujo sanguíneo o si hay sangrado en el cerebro. Los medicamentos para disolver coágulos pueden ayudar a algunos pacientes con un accidente cerebrovascular isquémico; no tratan un derrame hemorrágico. Consulte la <a href="'+nih+'">guía de tratamiento del NIH</a> (en inglés).</p>' if es else '<p>Treatment depends on whether a clot blocks blood flow or there is bleeding in the brain. Clot-busting medicines may help selected patients with an ischemic stroke; they do not treat a hemorrhagic stroke. See the <a href="'+nih+'">NIH treatment guidance</a>.</p>')
    current=setting(pid,'111e7fa0','editor');tail=re.findall(r'<p>[^<]*(?:Stroke recognition|El reconocimiento).*?</p>',current,re.S)
    assert len(tail)==1
    put(pid,'111e7fa0',why,editor=intro+treatment+tail[0])
    put(pid,'6931d24a',why,description_text_b=('Nuestro equipo de emergencia está disponible las 24 horas. Si sospecha un derrame cerebral, llame al 911 para que los servicios de emergencia determinen el destino adecuado.' if es else 'Our emergency team is available around the clock. For suspected stroke, call 911 so emergency responders can determine the appropriate destination.'))
    put(pid,'627745bb',why,description_text_b=('La tomografía ayuda a evaluar un posible sangrado cerebral. Los médicos interpretan las imágenes junto con los síntomas y la evaluación neurológica.' if es else 'CT imaging helps evaluate possible bleeding in the brain. Clinicians interpret the images alongside symptoms and the neurological examination.'))
    put(pid,'af6b803',why,title_text_a=('Evaluación del tratamiento' if es else 'Treatment Assessment'),title_text_b=('Evaluación del tratamiento' if es else 'Treatment Assessment'),description_text_b=('Las decisiones sobre medicamentos y procedimientos dependen del tipo de accidente cerebrovascular, el momento de inicio y la evaluación del equipo médico.' if es else 'Decisions about medicines and procedures depend on stroke type, symptom timing and the medical team’s assessment.'))
    put(pid,'641727e',why,description_text_b=('Coordinamos el traslado médico cuando se necesita atención neurológica, neurocirugía u otros servicios especializados.' if es else 'We coordinate medical transfer when neurological care, neurosurgery or other specialist services are needed.'))
    put(pid,'b492b12',why,editor=('<p>Ante señales de un accidente cerebrovascular:</p><ul><li><strong>Llame al 911:</strong> Pida una ambulancia de inmediato.</li><li><strong>Anote la hora:</strong> Indique cuándo comenzaron los síntomas o cuándo la persona estaba bien por última vez.</li><li><strong>Siga las instrucciones:</strong> Permanezca con la persona y siga las indicaciones del operador del 911.</li><li><strong>No espere:</strong> No deje pasar el tiempo para ver si los síntomas mejoran.</li></ul>' if es else '<p>If someone has stroke warning signs:</p><ul><li><strong>Call 911:</strong> Request an ambulance immediately.</li><li><strong>Record the time:</strong> Note when symptoms began or when the person was last known to be well.</li><li><strong>Follow instructions:</strong> Stay with the person and follow the 911 dispatcher’s directions.</li><li><strong>Do not wait:</strong> Do not delay to see whether symptoms improve.</li></ul>'))
    for wid in ['1db51ab2','bdf1e05','b6242da']:put(pid,wid,why,text=('Llame al 911' if es else 'Call 911'),link={**setting(pid,wid,'link'),'url':'tel:911'})
    put(pid,'640abf8',why,editor=('<p>Nuestro equipo evalúa los síntomas, brinda estabilización inicial y coordina la atención especializada cuando se necesita. La atención depende de la condición y las necesidades de cada paciente.</p>' if es else '<p>Our team evaluates symptoms, provides initial stabilization and coordinates specialist care when needed. Care depends on each patient’s condition and needs.</p>'))
    put(pid,'4270172',why,title=('Si sospecha un derrame cerebral, llame al 911' if es else 'Call 911 for a Suspected Stroke'))
    put(pid,'4f886c3',why,editor=('<p>El accidente cerebrovascular es una emergencia. Llame al 911, no conduzca y no espere una respuesta por teléfono o formulario de nuestra oficina.</p>' if es else '<p>Stroke is an emergency. Call 911, do not drive, and do not wait for an office callback or contact-form response.</p>'))
    raw=setting(pid,'d5977fa','html');m=re.search(r'(<script[^>]*>)(.*?)(</script>)',raw,re.S);assert m
    data=json.loads(m[2]);data['url']=posts[pid]['url'];data['inLanguage']='es' if es else 'en'
    data['description']=('Evaluación de emergencia, tomografía y estabilización inicial. Si sospecha un accidente cerebrovascular, llame al 911.' if es else 'Emergency evaluation, CT imaging and initial stabilization. Call 911 for a suspected stroke.')
    data['about']['description']=('El tratamiento depende del tipo de accidente cerebrovascular. Se coordina atención especializada y traslado según la evaluación médica.' if es else 'Treatment depends on stroke type. Specialist care and transfer are coordinated according to medical assessment.')
    put(pid,'d5977fa',why,html=m[1]+json.dumps(data,ensure_ascii=False,indent=2)+m[3])
    old=('requieren una evaluación inmediata en la sala de emergencias' if es else 'require an immediate ER evaluation')
    replace(pid,'4b311a94','editor',old,('requieren llamar al 911 de inmediato' if es else 'require calling 911 immediately'),why)
    if es:put(pid,'5fcfb78c','WR-01: replace directions to the wrong facility.',link=setting(1091,'793449b','link'))

# Preserve the confirmed multidisciplinary team, and keep update dates as update dates.
put(4667,'b22b0e6','WR-04: operator confirms a multidisciplinary review team; preserve collective credit.',editor='<p>Medically reviewed by the <a href="https://erofwhiterock.com/editorial-policy/">ER of White Rock Clinical Team</a> |</p>')
put(5391,'db32d1f','WR-04: align the policy with the operator-confirmed review team.',editor='<p><strong>Reviewed by the ER of White Rock clinical team.</strong> Our review team includes nurses, physicians and other healthcare practitioners. Learn about our team on the <a href="https://erofwhiterock.com/about-er-of-white-rock/">About page</a>.</p>')

# Correct unsupported surgery claims without asserting an unverified surgical service.
for pid,es in [(1091,False),(4717,True)]:
    why='WR-02: align appendicitis copy with the existing evaluation and surgical referral model.'
    if not es:replace(pid,'2e27245d','editor','Emergency Room of Dallas','ER of White Rock',why)
    put(pid,'6bdbae2f',why,editor=('<p><strong>ER of White Rock</strong> está abierto las <strong>24 horas</strong> para evaluar síntomas de apendicitis y brindar atención inicial. Si necesita cirugía, coordinamos la atención con especialistas y el traslado al centro adecuado.</p>' if es else '<p><strong>ER of White Rock</strong> is open <strong>24/7</strong> to evaluate appendicitis symptoms and provide initial care. If surgery is needed, we coordinate care with specialists and transfer to an appropriate facility.</p>'))
    replace(pid,'48c3a2d2','editor',site+'/services/laboratory-testing-services-in-white-rock/',posts[4745 if es else 852]['url'],'WR-06: imaging anchor must link to imaging, not laboratory testing.')
    replace(pid,'4e116328','editor',('atencion urgente para apendicitis cerca de mi en Dallas' if es else 'urgent care for appendicitis near me in Dallas'),('una evaluación de emergencia por posible apendicitis' if es else 'emergency evaluation for possible appendicitis'),why)
    put(pid,'18797cab',why,editor=('<p>Esto es lo que puede esperar durante una evaluación por posible apendicitis en <strong>ER of White Rock</strong>:</p>' if es else '<p>Here is what to expect during an evaluation for possible appendicitis at <strong>ER of White Rock</strong>:</p>'))
    put(pid,'6dd9b06e',why,title=('Evaluación inicial de apendicitis en White Rock' if es else 'Initial Appendicitis Evaluation in White Rock'))
    put(pid,'4b2e13e',why,editor=('<p>Si sospecha apendicitis, busque atención médica sin demora. Nuestro equipo evalúa los síntomas y determina qué pruebas y cuidados iniciales se necesitan.</p><p>Cuando se requiere cirugía o atención hospitalaria, coordinamos la atención con especialistas. No espere una respuesta a un formulario para buscar ayuda por síntomas urgentes.</p>' if es else '<p>If you suspect appendicitis, seek medical care without delay. Our team evaluates symptoms and determines which tests and initial treatments are needed.</p><p>When surgery or hospital care is required, we coordinate specialist care. Do not wait for a contact-form response before seeking help for urgent symptoms.</p>'))

# Imaging: remove unsubstantiated modality/specialist claims and appointment confusion.
for pid,es in [(852,False),(4745,True)]:
    why='WR-03: describe imaging in emergency care; remove unsupported MRI/mammography and specialist guarantees.'
    val=setting(pid,'1b7effd6','editor')
    tail=re.findall(r'<p>(?:On-site imaging|La imagenología).*?</p>',val,re.S);assert len(tail)==1
    intro=('<p>En <a href="https://erofwhiterock.com/es/home-page-er-of-white-rock/">ER of White Rock</a>, las imágenes diagnósticas apoyan la evaluación de enfermedades y lesiones urgentes. El equipo médico determina si necesita tomografía computarizada, radiografías o ultrasonido según sus síntomas y examen.</p>' if es else '<p>At <a href="https://erofwhiterock.com/">ER of White Rock</a>, diagnostic imaging supports the evaluation of urgent illnesses and injuries. The medical team determines whether CT, X-ray or ultrasound is appropriate for your symptoms and examination.</p><p>Learn about <a href="https://medlineplus.gov/diagnosticimaging.html">diagnostic imaging from MedlinePlus</a>.</p>')
    tail[0]=tail[0].replace('freestanding ER vs hospital ER guide near White Rock','guide to emergency care near White Rock').replace('guía de sala de emergencias independiente vs hospital cerca de White Rock','guía de atención de emergencia cerca de White Rock')
    put(pid,'1b7effd6',why,editor=intro+tail[0])
    put(pid,'18eeeb63',why,title=('Imágenes como parte de su evaluación de emergencia' if es else 'Imaging During Your Emergency Evaluation'))
    put(pid,'152a3ba0',why,editor=('<p>Puede acudir sin cita para recibir atención de emergencia. Un profesional evalúa sus síntomas antes de decidir qué estudios necesita. Para consultas sobre un estudio de rutina o una orden externa, llame primero y confirme la disponibilidad.</p>' if es else '<p>You can walk in for emergency care. A clinician evaluates your symptoms before deciding which imaging tests are needed. For routine scans or an outside imaging order, call first to confirm availability.</p>'))
    put(pid,'3c93f0ad',why,title_text_a=('Imágenes para niños' if es else 'Imaging for Children'),title_text_b=('Imágenes para niños' if es else 'Imaging for Children'),description_text_b=('El equipo considera la edad, los síntomas y la necesidad médica antes de solicitar imágenes para un niño. Puede preguntarnos cómo se realizará el estudio.' if es else 'The team considers a child’s age, symptoms and medical need before ordering imaging. Ask us how the scan will be performed.'))
    billing=posts[2198]['url']
    put(pid,'1c394420',why,editor=('<p>La cobertura, los deducibles y otros costos dependen de su plan y de los servicios recibidos. Nuestro centro figura como fuera de la red en nuestra <a href="'+billing+'">información de seguros y facturación</a> (en inglés). Para preguntas no urgentes, contacte a nuestro equipo de facturación o a su aseguradora.</p>' if es else '<p>Coverage, deductibles and other costs depend on your plan and the services you receive. Our facility is listed as out of network in our <a href="'+billing+'">insurance and billing information</a>. For non-urgent questions, contact our billing team or your insurer.</p>'))
    put(pid,'271fad8',why,title=('Preguntas sobre imágenes diagnósticas' if es else 'Questions About Diagnostic Imaging'))
    put(pid,'b03a203',why,editor=('<p>Llame para consultas no urgentes sobre imágenes, órdenes externas o facturación. Si cree que tiene una emergencia que pone en peligro su vida, llame al 911.</p>' if es else '<p>Call for non-urgent questions about imaging, outside orders or billing. If you think you have a life-threatening emergency, call 911.</p>'))
    core(pid,'post_title',('Imágenes Diagnósticas en White Rock | ER of White Rock' if es else 'Diagnostic Imaging in White Rock | ER of White Rock'),why)
    seo(pid,'title',('Imágenes Diagnósticas en White Rock | Atención 24/7' if es else 'Diagnostic Imaging in White Rock | Emergency Care 24/7'),why)
    seo(pid,'description',('Imágenes diagnósticas para atención de emergencia en White Rock. El equipo médico determina qué estudios necesita según sus síntomas y evaluación.' if es else 'Diagnostic imaging for emergency care in White Rock. Our medical team determines which tests are appropriate for your symptoms and examination.'),why)

# Replace unrelated TBI warnings while preserving each form and routing settings.
for pid,es,wid in [(971,False,'6fe9fbe'),(4747,True,'6fe9fbe'),(2482,False,'898932f'),(4739,True,'898932f'),(868,False,'6b00912'),(4705,True,'6b00912')]:
    put(pid,wid,'WR-09: correct copied contact warning; contact forms are for non-urgent questions.',editor=('<p>Use este formulario para preguntas de rutina y <a href="https://erofwhiterock.com/insurance-billing-info/">consultas de facturación</a>. No se supervisa para emergencias. Si cree que tiene una emergencia médica, llame al 911 y no espere una respuesta por este medio.</p>' if es else '<p>Use this form for routine questions and <a href="https://erofwhiterock.com/insurance-billing-info/">billing inquiries</a>. It is not monitored for emergencies. If you think you have a medical emergency, call 911 and do not wait for a form response.</p>'))

# Remove absolute wait guarantees on the ten neighborhood pages. Preserve local sections.
for pid in [5496,5495,5470,5471,5469,5468,5466,5463,5462,5460]:
    es=pid in [5496,5470,5471,5469,5468]
    for wid,n in nodes[pid].items():
        old=n['settings'].get('editor','')
        if re.search(r'(?:[Mm]edian door-to-provider time|tiempo medio de puerta a proveedor)',old):
            put(pid,wid,'WR-05: remove unsupported every-visit wait guarantee.',editor=('<p>La atención comienza con una evaluación de sus síntomas. Los tiempos de espera y de las pruebas varían según sus necesidades y la demanda de atención.</p>' if es else '<p>Care begins with an assessment of your symptoms. Waiting and testing times vary with your medical needs and current demand for care.</p>'))

# Home: align operational copy with the site's actual billing disclosure, avoid blanket credential claims.
for pid,es in [(228,False),(4687,True)]:
    why='WR-05: qualify operational claims and align insurance wording with published facility disclosures.'
    put(pid,'87eca3b',why,description_text=('Nuestro equipo brinda atención de emergencia y se comunica con usted sobre la evaluación, el tratamiento y los siguientes pasos.' if es else 'Our team provides emergency care and explains your evaluation, treatment and next steps.'))
    put(pid,'ce2fe0e',why,description_text=('Nuestro equipo incluye médicos de emergencia y personal de enfermería que atienden enfermedades y lesiones urgentes.' if es else 'Our team includes emergency physicians and nursing staff who care for urgent illnesses and injuries.'))
    replace(pid,'2218e78','editor',('Un ambiente limpio, tranquilo y menos intimidante que los hospitales tradicionales de la ciudad.' if es else 'A clean, quiet, and less intimidating atmosphere than traditional city hospitals.'),('Un ambiente tranquilo pensado para ayudar a los niños y sus familias a sentirse acompañados.' if es else 'A calm environment designed to support children and their families.'),why)
    put(pid,'467b1f3',why,editor=('<p>Aceptamos la mayoría de los planes privados y comerciales, pero nuestro centro está fuera de la red. Aceptar un seguro no significa participar en su red. Consulte los detalles de su cobertura y costos con su aseguradora y revise nuestra <a href="https://erofwhiterock.com/insurance-billing-info/">información de facturación</a> (en inglés).</p>' if es else '<p>We accept most private and commercial insurance plans, but our facility is out of network. Accepting insurance does not mean participating in its network. Check your coverage and costs with your insurer and read our <a href="https://erofwhiterock.com/insurance-billing-info/">billing information</a>.</p>'))
    put(pid,'1663f42',why,title=('<b>Aviso:</b> ER of White Rock no participa en Medicare, Medicaid ni TRICARE.' if es else '<b>Notice:</b> ER of White Rock does not participate in Medicare, Medicaid or TRICARE.'))
    seo(pid,'description',('ER of White Rock ofrece atención de emergencia las 24 horas en 10705 Northwest Hwy, Dallas. Conozca nuestros servicios, ubicación y datos de contacto.' if es else 'ER of White Rock provides emergency care 24/7 at 10705 Northwest Hwy, Dallas. Find our services, location, contact details and insurance information.'),why)

# Existing English lab corrections already approved remain untouched.
put(4747,'46afe405','WR-09: bring the Spanish results heading into line with the approved English correction.',title_text_a='Cómo entender sus resultados',title_text_b='Cómo entender sus resultados')
put(4747,'77684669','WR-09: translate the approved English explanation of clinical interpretation.',description_text_b='Su médico considera los resultados junto con sus síntomas, antecedentes y examen. Pregunte qué significan los hallazgos y si necesita seguimiento.')
put(4747,'2a6ed6d9','WR-09: translate the approved English explanation of test limitations.',description_text_b='Las pruebas de laboratorio aportan información para evaluar sus síntomas. Algunos resultados pueden requerir pruebas adicionales o seguimiento.')
put(4747,'1ffbaf62','WR-09: remove perfect-result and fixed-turnaround promises in Spanish.',editor='<p>El laboratorio apoya la evaluación de emergencia. Su médico decide qué pruebas se necesitan y le explica los resultados disponibles.</p><ul><li><strong>Interpretación clínica:</strong> Ninguna prueba es perfecta. Los resultados se consideran junto con los síntomas y el examen.</li><li><strong>Tiempos variables:</strong> Algunas pruebas tardan más o requieren estudios adicionales. Pregunte cuándo y cómo recibirá los resultados pendientes.</li><li><strong>Facturación:</strong> Consulte nuestra <a href="https://erofwhiterock.com/insurance-billing-info/">información de seguros y facturación</a>.</li><li><strong>Privacidad:</strong> Consulte nuestra política de privacidad para conocer cómo se maneja su información.</li></ul>')
for pid,es in [(971,False),(4747,True)]:
    why='WR-09: remove laboratory certainty/turnaround guarantees; preserve diagnostic context.'
    put(pid,'8a94fb1',why,editor=('<p>Las pruebas de laboratorio ayudan al equipo médico a evaluar sus síntomas y decidir los siguientes pasos.</p>' if es else '<p>Laboratory tests help the medical team assess your symptoms and decide the next steps in your care.</p>'))
    put(pid,'75441c43',why,editor=('<p>ER of White Rock ofrece pruebas de laboratorio como parte de la atención de emergencia. Pregunte qué significa cada resultado y si necesita seguimiento.</p>' if es else '<p>ER of White Rock provides laboratory testing as part of emergency care. Ask what each result means and whether follow-up is needed.</p>'))
    put(pid,'260980fe',why,editor=('<p>Los análisis de sangre y orina aportan información para evaluar una enfermedad o lesión. El médico determina cuáles son necesarios.</p><ul><li><strong>Hemograma:</strong> Mide las células sanguíneas y puede aportar indicios de anemia o infección.</li><li><strong>Panel metabólico:</strong> Ayuda a evaluar glucosa, electrolitos y función de órganos.</li><li><strong>Pruebas cardíacas y de coagulación:</strong> Se interpretan junto con los síntomas, el examen y otras pruebas. Un resultado aislado no descarta todas las emergencias.</li><li><strong>Análisis de orina:</strong> Puede ayudar a evaluar síntomas urinarios. El tratamiento depende de los hallazgos y la evaluación médica.</li></ul>' if es else '<p>Blood and urine tests provide information to help evaluate an illness or injury. Your clinician decides which tests are needed.</p><ul><li><strong>Complete blood count:</strong> Measures blood cells and can offer clues about anemia or infection.</li><li><strong>Metabolic panel:</strong> Helps assess glucose, electrolytes and organ function.</li><li><strong>Cardiac and coagulation tests:</strong> Are interpreted alongside symptoms, examination and other tests. One result does not rule out every emergency.</li><li><strong>Urinalysis:</strong> May help evaluate urinary symptoms. Treatment depends on the findings and medical assessment.</li></ul>'))
    for wid,phrase in [('231ddd11','resultados precisos en minutos' if es else 'precise results in minutes'),('407ce12a','La mayoría de los resultados de pruebas críticas se entregan directamente a su médico tratante dentro de una hora.' if es else 'Most critical test results are delivered straight to your attending physician within one hour.')]:
        replace(pid,wid,'editor',phrase,('resultados que apoyan la evaluación médica' if es else 'results that support clinical evaluation') if wid=='231ddd11' else ('Los tiempos varían según la prueba. Antes de irse, pregunte qué resultados siguen pendientes y cómo recibirá el seguimiento.' if es else 'Timing varies by test. Before leaving, ask which results are still pending and how follow-up will be arranged.'),why)
    replace(pid,'231ddd11','editor',('guía de sala de emergencias independiente vs hospital cerca de White Rock' if es else 'freestanding ER vs hospital ER guide near White Rock'),('guía de atención de emergencia cerca de White Rock' if es else 'guide to emergency care near White Rock'),why)
    put(pid,'7f81724',why,description_text_b=('Las pruebas de COVID-19 ayudan a evaluar una posible infección. Su médico interpreta los resultados según los síntomas y el momento de la prueba.' if es else 'COVID-19 tests help assess possible infection. Your clinician interprets results in light of symptoms and test timing.'))
    put(pid,'5a5be3b',why,description_text_b=('Un hemograma mide las células sanguíneas y puede aportar información sobre anemia o infección. Se interpreta junto con otros hallazgos clínicos.' if es else 'A complete blood count measures blood cells and can provide clues about anemia or infection. It is interpreted alongside other clinical findings.'))
    put(pid,'4e089f1',why,description_text_b=('Las pruebas de estreptococo o mononucleosis pueden ayudar a evaluar un dolor de garganta. El equipo determina cuáles son adecuadas según sus síntomas.' if es else 'Strep or mononucleosis tests can help evaluate a sore throat. The team determines which tests are appropriate for your symptoms.'))
    for wid in ['2a6e509','6fe9fbe']:
        css=setting(pid,wid,'custom_css') or ''
        put(pid,wid,'WR-13: white underlined links on the existing navy section meet text contrast requirements.',custom_css=css+'\nselector a, selector a:hover, selector a:focus { color: #FFFFFF !important; text-decoration: underline; }\nselector a:focus-visible { outline: 2px solid #FFFFFF; outline-offset: 3px; }')

def write():
    requests=[{'name':'replace_section_content','arguments':{'post_id':pid,'section_id':'','widget_updates':[{'widget_id':w,'settings':s} for w,s in widgets.items()],'reasoning':' '.join(reasons[pid])}} for pid,widgets in updates.items()]
    requests+=extras
    (root/'content-proposals.json').write_text(json.dumps(requests,ensure_ascii=False,indent=2),encoding='utf-8')
    (root/'change-log.json').write_text(json.dumps(change_log,ensure_ascii=False,indent=2),encoding='utf-8')
    (root/'source-posts.json').write_text(json.dumps(posts,ensure_ascii=False),encoding='utf-8')
    for i in range(0,len(requests),8):(root/f'queue-{i//8}.json').write_text(json.dumps(requests[i:i+8],ensure_ascii=False),encoding='utf-8')
    print(json.dumps({'posts':len(updates),'widgets':sum(map(len,updates.values())),'field_edits':len(change_log),'proposals':len(requests),'batches':(len(requests)+7)//8}))
if __name__=='__main__':write()
