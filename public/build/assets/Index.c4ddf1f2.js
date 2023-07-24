import{h as f,K as te,V as M,j as ue,f as u,a as s,u as y,w as r,F as I,o as i,Z as ce,b as t,P as k,d as a,c as V,m as c,t as l,l as Y,O as Z,ab as me,n as g}from"./app.78c05cee.js";import{_ as pe}from"./Authenticated.7136ef3d.js";import{_ as K}from"./Button.f623bf86.js";import _e from"./ChannelOverview.a87b71a2.js";import ge from"./Create.e4b5d347.js";import ye from"./Form.084878cd.js";import{_ as J,a as ve,b as v}from"./TableData.1fa2a552.js";import{_ as T,r as fe}from"./SearchInput.55bf778f.js";import{_ as S}from"./MultiSelect.d56e6b23.js";import{_ as C}from"./TableHeadSort.f6ba0713.js";import{r as xe}from"./BackspaceIcon.0f0937c0.js";import{r as be}from"./ArrowDownTrayIcon.2c8cc00c.js";import{r as he}from"./PlusCircleIcon.e277cd89.js";import{r as ke}from"./PencilSquareIcon.6b36d6d6.js";import"./open-closed.7b1bf5f8.js";import"./use-resolve-button-type.72702426.js";import"./RectangleStackIcon.37958af7.js";import"./Modal.367bafd2.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.b74d754e.js";import"./FormInput.4a2872af.js";import"./ArrowUturnLeftIcon.96db235e.js";import"./DatePicker.38118c02.js";import"./main.51f9071c.js";const Te=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),Ce={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Ve={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Je=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),we=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),Be={key:2},Ke=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),$e={key:3},De=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Pe={key:4},je=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),Ne=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Fe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Me={key:5},Ie=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),Ee=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),Ue={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ge={class:"mt-3"},He={class:"flex flex-col md:flex-row"},Ae=t("span",null," Search ",-1),qe=t("span",null," Reset ",-1),Re={class:"flex space-x-1"},Ye={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},Ze=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),ze=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),Qe=[Ze,ze],We=t("span",null," Export Channels Excel ",-1),Xe=t("span",null," New Machine ",-1),et={class:"flex flex-col space-y-1"},tt={class:"text-sm text-gray-700 leading-5"},ot={class:"text-sm text-gray-700 leading-5 flex space-x-1"},nt=t("span",null,"Showing",-1),st={class:"font-medium"},at=t("span",null,"to",-1),lt={class:"font-medium"},rt=t("span",null,"of",-1),it={class:"font-medium"},dt=t("span",null,"results",-1),ut={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},ct={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},mt=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales (Last 30 days)",-1),pt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},_t={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},gt=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per VM (Last 30 days)",-1),yt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},vt={class:"mt-6 flex flex-col"},ft={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},xt={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},bt={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ht={class:"bg-gray-100"},kt={class:"divide-x divide-gray-200"},Tt=t("br",null,null,-1),Ct=t("br",null,null,-1),St=t("br",null,null,-1),Vt=t("br",null,null,-1),Jt=t("br",null,null,-1),wt=t("br",null,null,-1),Lt=t("br",null,null,-1),Bt=t("br",null,null,-1),Kt=t("br",null,null,-1),$t=t("br",null,null,-1),Dt={class:"bg-white"},Ot={key:0},Pt={key:0},jt=["href"],Nt=t("br",null,null,-1),Ft={key:1},Mt=t("br",null,null,-1),It={key:1},Et={class:"flex flex-col items-center"},Ut=["onClick"],Gt={class:"mt-1"},Ht=["onClick"],At={class:"text-blue-600"},qt={class:"flex flex-col"},Rt={class:"font-bold"},Yt=t("br",null,null,-1),Zt=t("br",null,null,-1),zt=t("br",null,null,-1),Qt=t("br",null,null,-1),Wt=t("br",null,null,-1),Xt={class:"flex flex-col space-y-1"},eo={class:"flex flex-col"},to={class:"font-bold"},oo={key:0},no={class:"flex flex-col"},so=t("span",{class:"font-bold"}," Drop Sensor ",-1),ao={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},lo={class:"flex flex-col"},ro=t("span",{class:"font-bold"}," Fan Speed ",-1),io={class:"flex flex-col"},uo=t("span",{class:"font-bold"}," Door ",-1),co={class:"flex flex-col"},mo=t("span",{class:"font-bold"}," Coin ",-1),po={key:4,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},_o=t("div",{class:"flex flex-col"},[t("span",{class:"font-bold"}," MQTT ")],-1),go=[_o],yo={key:0},vo=t("br",null,null,-1),fo=t("br",null,null,-1),xo=t("br",null,null,-1),bo={class:"flex flex-col items-center space-y-1"},ho=["onClick"],ko=["onClick"],To=["onClick"],Co={key:0,class:"text-blue-600"},So=t("br",null,null,-1),Vo={key:0},Jo={key:0},wo={key:1},Lo=t("br",null,null,-1),Bo={key:2},Ko=t("br",null,null,-1),$o={class:"flex justify-center space-x-1"},Do=t("span",null," Edit ",-1),Oo={key:0},Po=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),jo=[Po],ln={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,locationTypeOptions:Object,operatorOptions:Object,productOptions:Object,totals:[Array,Object],vends:Object,vendChannelErrors:Object},setup(d){const L=d,o=f({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],locationType:"",operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",lastVisitedGreaterThan:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",balanceStockLessThan:"",remainingSkuLessThan:"",sortKey:"",sortBy:!1,numberPerPage:"",visited:!0}),w=f([]),z=f([]),Q=f([]),E=f([]),U=f([]),$=f(!1),G=f([]),H=f([]),A=f([]),D=f(!1),O=f(!1),P=f(!1),j=f(""),B=f(),q=f([]),R=te().props.auth.operatorRole,b=te().props.auth.permissions,W=f(M().format("HH:mm:ss"));ue(()=>{o.value.visited=!0,q.value=[{id:"errors_only",desc:"Errors Only"},...L.vendChannelErrors.data],H.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=q.value[0],o.value.numberPerPage=H.value[0],z.value=L.categories.data.map(m=>({id:m.id,name:m.name})),Q.value=L.categoryGroups.data.map(m=>({id:m.id,name:m.name})),w.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],U.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],E.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],G.value=[{id:"all",value:"All"},...L.locationTypeOptions.data.map(m=>({id:m.id,value:m.name}))],A.value=[{id:"all",full_name:"All"},...L.operatorOptions.data.map(m=>({id:m.id,full_name:m.full_name}))],o.value.is_door_open=E.value[0],o.value.is_online=w.value[0],o.value.is_sensor=U.value[0],o.value.locationType=G.value[0],o.value.operator=A.value[0],R.value=="admin"||R.value=="supervisor"||R.value=="driver"?o.value.is_binded_customer=w.value[1]:o.value.is_binded_customer=w.value[0]});function oe(m){B.value=m,D.value=!0}function ne(){D.value=!1}function se(){j.value="create",B.value=null,O.value=!0}function ae(){O.value=!1}function le(m){j.value="edit",B.value=m,P.value=!0}function re(){P.value=!1}function x(){Z.get("/vends",{...o.value,categories:o.value.categories.map(m=>m.id),categoryGroups:o.value.categoryGroups.map(m=>m.id),errors:o.value.errors.map(m=>m.id),location_type_id:o.value.locationType.id,operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:m=>{W.value=M().format("HH:mm:ss")}})}function N(m,n){Z.get("/vends/"+m+"/temp/"+n)}function ie(){Z.get("/vends")}function h(m){o.value.sortKey=m,o.value.sortBy=!o.value.sortBy,x()}function de(){$.value=!0,me({method:"get",url:"/vends/channels/excel",params:{...o.value,categories:o.value.categories.map(m=>m.id),categoryGroups:o.value.categoryGroups.map(m=>m.id),errors:o.value.errors.map(m=>m.id),location_type_id:o.value.locationType.id,operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id},responseType:"blob"}).then(m=>{fileDownload(m.data,"Vending_Channels_"+M().format("YYMMDDhhmmss")+".xlsx")}).catch(m=>{console.log(m)}).finally(()=>{$.value=!1})}return(m,n)=>(i(),u(I,null,[s(y(ce),{title:"Vending Machines"}),s(pe,null,{header:r(()=>[Te]),default:r(()=>{var X,ee;return[t("div",Ce,[t("div",Se,[t("div",Ve,[s(T,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":n[0]||(n[0]=e=>o.value.codes=e),onKeyup:n[1]||(n[1]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Vend ID "),Je]),_:1},8,["modelValue"]),s(T,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":n[2]||(n[2]=e=>o.value.channel_codes=e),onKeyup:n[3]||(n[3]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Channel ID "),we]),_:1},8,["modelValue"]),s(T,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":n[4]||(n[4]=e=>o.value.serialNum=e),onKeyup:n[5]||(n[5]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Serial Num ")]),_:1},8,["modelValue"]),s(T,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":n[6]||(n[6]=e=>o.value.tempHigherThan=e),onKeyup:n[7]||(n[7]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Temp >> ")]),_:1},8,["modelValue"]),s(T,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":n[8]||(n[8]=e=>o.value.tempDeltaHigherThan=e),onKeyup:n[9]||(n[9]=k(e=>x(),["enter"]))},{default:r(()=>[a(" t1-t2 Delta >> ")]),_:1},8,["modelValue"]),t("div",null,[Le,s(S,{modelValue:o.value.errors,"onUpdate:modelValue":n[10]||(n[10]=e=>o.value.errors=e),options:q.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),y(b).includes("admin-access vends")?(i(),V(T,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":n[11]||(n[11]=e=>o.value.customer_code=e),onKeyup:n[12]||(n[12]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Cust ID ")]),_:1},8,["modelValue"])):c("",!0),y(b).includes("admin-access vends")?(i(),V(T,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":n[13]||(n[13]=e=>o.value.customer_name=e),onKeyup:n[14]||(n[14]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Cust Name ")]),_:1},8,["modelValue"])):c("",!0),y(b).includes("admin-access vends")?(i(),u("div",Be,[Ke,s(S,{modelValue:o.value.categories,"onUpdate:modelValue":n[15]||(n[15]=e=>o.value.categories=e),options:z.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):c("",!0),y(b).includes("admin-access vends")?(i(),u("div",$e,[De,s(S,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":n[16]||(n[16]=e=>o.value.categoryGroups=e),options:Q.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):c("",!0),t("div",null,[Oe,s(S,{modelValue:o.value.is_online,"onUpdate:modelValue":n[17]||(n[17]=e=>o.value.is_online=e),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),y(b).includes("admin-access vends")?(i(),u("div",Pe,[je,s(S,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":n[18]||(n[18]=e=>o.value.is_binded_customer=e),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):c("",!0),t("div",null,[Ne,s(S,{modelValue:o.value.is_sensor,"onUpdate:modelValue":n[19]||(n[19]=e=>o.value.is_sensor=e),options:U.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Fe,s(S,{modelValue:o.value.is_door_open,"onUpdate:modelValue":n[20]||(n[20]=e=>o.value.is_door_open=e),options:E.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(T,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":n[21]||(n[21]=e=>o.value.fanSpeedLowerThan=e),onKeyup:n[22]||(n[22]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Fan Speed << ")]),_:1},8,["modelValue"]),y(b).includes("admin-access vends")?(i(),u("div",Me,[Ie,s(S,{modelValue:o.value.operator,"onUpdate:modelValue":n[23]||(n[23]=e=>o.value.operator=e),options:A.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):c("",!0),t("div",null,[Ee,s(S,{modelValue:o.value.locationType,"onUpdate:modelValue":n[24]||(n[24]=e=>o.value.locationType=e),options:G.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(T,{placeholderStr:"How many Day(s)",modelValue:o.value.lastVisitedGreaterThan,"onUpdate:modelValue":n[25]||(n[25]=e=>o.value.lastVisitedGreaterThan=e),onKeyup:n[26]||(n[26]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Last Visited Day >> ")]),_:1},8,["modelValue"]),s(T,{placeholderStr:"Balance Stock Less Than",modelValue:o.value.balanceStockLessThan,"onUpdate:modelValue":n[27]||(n[27]=e=>o.value.balanceStockLessThan=e),onKeyup:n[28]||(n[28]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Balance Stock(%) << ")]),_:1},8,["modelValue"]),s(T,{placeholderStr:"Remaining SKU Less Than",modelValue:o.value.remainingSkuLessThan,"onUpdate:modelValue":n[29]||(n[29]=e=>o.value.remainingSkuLessThan=e),onKeyup:n[30]||(n[30]=k(e=>x(),["enter"]))},{default:r(()=>[a(" Remaining SKU(%) << ")]),_:1},8,["modelValue"])]),t("div",Ue,[t("div",Ge,[t("div",He,[s(K,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[31]||(n[31]=e=>x())},{default:r(()=>[s(y(fe),{class:"h-4 w-4","aria-hidden":"true"}),Ae]),_:1}),s(K,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[32]||(n[32]=e=>ie())},{default:r(()=>[s(y(xe),{class:"h-4 w-4","aria-hidden":"true"}),qe]),_:1}),y(b).includes("export excel")?(i(),V(K,{key:0,type:"button",class:"rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100",onClick:n[33]||(n[33]=e=>de())},{default:r(()=>[t("div",Re,[t("div",null,[$.value?c("",!0):(i(),V(y(be),{key:0,class:"h-4 w-4","aria-hidden":"true"})),$.value?(i(),u("svg",Ye,Qe)):c("",!0)]),We])]),_:1})):c("",!0),y(b).includes("admin-access vends")?(i(),V(K,{key:1,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-yellow-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[34]||(n[34]=e=>se())},{default:r(()=>[s(y(he),{class:"h-4 w-4","aria-hidden":"true"}),Xe]),_:1})):c("",!0)])]),t("div",et,[t("span",tt,[t("p",null,"Last loaded: "+l(W.value),1)]),t("p",ot,[nt,t("span",st,l((X=d.vends.meta.from)!=null?X:0),1),at,t("span",lt,l((ee=d.vends.meta.to)!=null?ee:0),1),rt,t("span",it,l(d.vends.meta.total),1),dt]),s(S,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":n[35]||(n[35]=e=>o.value.numberPerPage=e),options:H.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:x},null,8,["modelValue","options"])])]),t("dl",ut,[t("div",ct,[mt,t("dd",pt,l(d.totals.thirtyDays.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",_t,[gt,t("dd",yt,l((d.totals.thirtyDays/d.vends.meta.to?d.totals.thirtyDays/d.vends.meta.to:0).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)])])]),t("div",vt,[t("div",ft,[t("div",xt,[t("table",bt,[t("thead",ht,[t("tr",kt,[s(J,null,{default:r(()=>[a(" # ")]),_:1}),s(C,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[36]||(n[36]=e=>h("vends.code"))},{default:r(()=>[a(" ID ")]),_:1},8,["sortKey","sortBy"]),s(J,null,{default:r(()=>[a(" Name ")]),_:1}),s(C,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[37]||(n[37]=e=>h("temp"))},{default:r(()=>[a(" Temp1(\u2103) "),Tt,a(" \u0394t1-t2 ")]),_:1},8,["sortKey","sortBy"]),s(J,null,{default:r(()=>[a(" Inventory Status "),Ct,a(" (#Channel, Sold, Balance/Capacity) ")]),_:1}),s(J,null,{default:r(()=>[a(" Errors ")]),_:1}),s(C,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[38]||(n[38]=e=>h("vend_channel_totals_json->balancePercent"))},{default:r(()=>[a(" Balance Stock ")]),_:1},8,["sortKey","sortBy"]),s(C,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[39]||(n[39]=e=>h("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[a(" Remaining SKU# ")]),_:1},8,["sortKey","sortBy"]),s(C,{modelName:"vend_transaction_totals_json->thirty_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[40]||(n[40]=e=>h("vend_transaction_totals_json->thirty_days_amount"))},{default:r(()=>[a(" $ Sales (qty)"),St,a(" Today "),Vt,a(" Y'day"),Jt,a(" Last7d "),wt,a(" Last30d ")]),_:1},8,["sortKey","sortBy"]),s(J,null,{default:r(()=>[a(" Status ")]),_:1}),s(C,{modelName:"last_invoice_date",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[41]||(n[41]=e=>h("last_invoice_date"))},{default:r(()=>[a(" Last Visited ")]),_:1},8,["sortKey","sortBy"]),s(C,{modelName:"next_invoice_date",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[42]||(n[42]=e=>h("next_invoice_date"))},{default:r(()=>[a(" Next Planned Visit ")]),_:1},8,["sortKey","sortBy"]),s(C,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[43]||(n[43]=e=>h("parameter_json->t2"))},{default:r(()=>[a(" Temp2 "),Lt,a(" (Evap)"),Bt,a(" \u2103 ")]),_:1},8,["sortKey","sortBy"]),s(C,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[44]||(n[44]=e=>h("postcode"))},{default:r(()=>[a(" Postcode ")]),_:1},8,["sortKey","sortBy"]),s(J,null,{default:r(()=>[a(" Firmware Ver ")]),_:1}),s(C,{modelName:"location_type_name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[45]||(n[45]=e=>h("location_type_name"))},{default:r(()=>[a(" Location ")]),_:1},8,["sortKey","sortBy"]),s(C,{modelName:"vend_transaction_totals_json->vend_records_amount_latest",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[46]||(n[46]=e=>h("vend_transaction_totals_json->vend_records_amount_latest"))},{default:r(()=>[a(" Lifetime Sales ($)"),Kt,a(" Begin Date "),$t,a(" Avg Sales/ Day ($) ")]),_:1},8,["sortKey","sortBy"]),s(J)])]),t("tbody",Dt,[(i(!0),u(I,null,Y(d.vends.data,(e,_)=>(i(),u("tr",{key:_,class:"divide-x divide-gray-200"},[s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(d.vends.meta.from+_),1)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.customer_code?(i(),u("span",Ot,[y(b).includes("admin-access vends")?(i(),u("span",Pt,[t("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/vend-code/"+e.code},[a(l(e.customer_code)+" ",1),Nt,a(" "+l(e.customer_name),1)],8,jt)])):(i(),u("span",Ft,[a(l(e.customer_code)+" ",1),Mt,a(" "+l(e.customer_name),1)]))])):(i(),u("span",It,l(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Et,[e.temp_updated_at?(i(),u("button",{key:0,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>N(e.id,1)},l(e.is_temp_error?"Error":e.temp),11,Ut)):c("",!0),t("span",Gt,l(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=d.constTempError&&!e.is_temp_error?(i(),u("span",{key:1,class:g(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},l((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):c("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(i(),u("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:p=>oe(e)},[(i(!0),u(I,null,Y(e.vendChannelsJson,(p,F)=>(i(),u("li",{class:g(["quick-look",[F>0&&String(p.code)[0]!==String(e.vendChannelsJson[F-1].code)[0]?"col-start-1":""]])},[t("span",{class:g([F>0&&String(p.code)[0]!==String(e.vendChannelsJson[F-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+l(p.code)+", ",1),t("span",At,l(p.capacity-p.qty)+", ",1),t("span",{class:g([p.qty<=2?"text-red-700":"text-green-700"])},l(p.qty)+"/"+l(p.capacity),3)],2)],2))),256))],8,Ht)):c("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[(i(!0),u(I,null,Y(e.vendChannelErrorLogsJson,p=>(i(),u("span",{class:g(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[p.vendChannelError?p.vendChannelError.code==4||p.vendChannelError.code==5||p.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":p.vend_channel.code==4||p.vend_channel.code==5||p.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",qt,[t("div",null,[a(" #"+l(p.vendChannel?p.vendChannel.code:p.vend_channel.code)+", ",1),t("span",Rt," ("+l(p.vendChannelError?p.vendChannelError.code:p.vend_channel_error.code)+") ",1)]),t("div",null,l(p.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:g([e.vendChannelTotalsJson.balancePercent<=15?"text-red-700":e.vendChannelTotalsJson.balancePercent>40?"text-green-700":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.qty)+"/ "+l(e.vendChannelTotalsJson.capacity)+" ",1),Yt,a(" ("+l(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):c("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(i(),u("span",{key:0,class:g([100-e.vendChannelTotalsJson.outOfStockSkuPercent<=25?"text-red-700":100-e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-green-700":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.count-e.vendChannelTotalsJson.outOfStockSku)+"/ "+l(e.vendChannelTotalsJson.count)+" ",1),Zt,a(" ("+l(100-e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):c("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendTransactionTotalsJson&&"today_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:0,class:g([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},l((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)):c("",!0),e.vendTransactionTotalsJson&&"yesterday_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:1,class:g([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[zt,a(" "+l((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):c("",!0),e.vendTransactionTotalsJson&&"seven_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:2,class:g([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},[Qt,a(" "+l((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):c("",!0),e.vendTransactionTotalsJson&&"thirty_days_amount"in e.vendTransactionTotalsJson?(i(),u("span",{key:3,class:g([e.vendTransactionTotalsJson.thirty_days_amount/100>1e3?"text-green-700":"text-red-700"])},[Wt,a(" "+l((e.vendTransactionTotalsJson.thirty_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.thirty_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):c("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Xt,[t("div",{class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",eo,[t("span",to,l(e.is_online?"Online":"Offline"),1),e.last_updated_at?(i(),u("span",oo,l(e.last_updated_at),1)):c("",!0)])],2),e.parameterJson?(i(),u("div",{key:0,class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",no,[so,t("span",null,l(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):c("",!0),e.parameterJson&&"fan"in e.parameterJson?(i(),u("div",ao,[t("div",lo,[ro,t("span",null,l(e.parameterJson.fan),1)])])):c("",!0),e.parameterJson&&e.parameterJson.door?(i(),u("div",{key:2,class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",io,[uo,t("span",null,l(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):c("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(i(),u("div",{key:3,class:g(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",co,[mo,t("span",null,l((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):c("",!0),e.is_mqtt?(i(),u("div",po,go)):c("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.cms_invoice_history&&"last_delivery_driver"in e.cms_invoice_history?(i(),u("span",yo,[a(l(e.cms_invoice_history.last_delivery_driver)+" ",1),vo])):c("",!0),t("span",null,[a(l(e.last_invoice_date)+" ",1),fo,a(" "+l(e.last_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",null,[a(l(e.next_invoice_date)+" ",1),xo,a(" "+l(e.next_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",bo,[e.parameterJson&&"t2"in e.parameterJson?(i(),u("button",{key:0,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>N(e.id,2)},l(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,ho)):c("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=d.constTempError?(i(),u("button",{key:1,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>N(e.id,3)},l(e.parameterJson.t3==d.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,ko)):c("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=d.constTempError?(i(),u("button",{key:2,type:"button",class:g(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>N(e.id,4)},l(e.parameterJson.t4==d.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,To)):c("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.postcode),1)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.parameterJson&&e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(i(),u("span",Co,[So,a("Apk: "+l(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(i(),u("span",Vo,l(y(M)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):c("",!0)])):c("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.location_type_name),1)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendTransactionTotalsJson&&"vend_records_amount_latest"in e.vendTransactionTotalsJson?(i(),u("span",Jo,l((e.vendTransactionTotalsJson.vend_records_amount_latest/100).toLocaleString(void 0,{minimumFractionDigits:2})),1)):c("",!0),e.begin_date?(i(),u("span",wo,[Lo,a(" "+l(e.begin_date),1)])):c("",!0),e.vendTransactionTotalsJson&&"vend_records_amount_average_day"in e.vendTransactionTotalsJson?(i(),u("span",Bo,[Ko,a(" "+l((e.vendTransactionTotalsJson.vend_records_amount_average_day/100).toLocaleString(void 0,{minimumFractionDigits:2})),1)])):c("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(v,{currentIndex:_,totalLength:d.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",$o,[s(K,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:p=>le(e)},{default:r(()=>[s(y(ke),{class:"w-4 h-4"}),Do]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?c("",!0):(i(),u("tr",Oo,jo))])])]),d.vends.data.length?(i(),V(ve,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):c("",!0)])])]),D.value?(i(),V(_e,{key:0,productOptions:d.productOptions,vend:B.value,showModal:D.value,onModalClose:ne},null,8,["productOptions","vend","showModal"])):c("",!0),P.value?(i(),V(ye,{key:1,vend:B.value,type:j.value,showModal:P.value,permissions:y(b),onModalClose:re},null,8,["vend","type","showModal","permissions"])):c("",!0),O.value?(i(),V(ge,{key:2,showModal:O.value,permissions:y(b),type:j.value,onModalClose:ae},null,8,["showModal","permissions","type"])):c("",!0)]}),_:1})],64))}};export{ln as default};