import{o as r,f as c,b as t,r as ve,l as m,g as v,K,U as P,h as fe,a,u as _,w as i,F as E,Z as xe,N as k,d as l,c as T,t as s,k as W,O as X,aa as ae,n as y}from"./app.8d489fd7.js";import{_ as he}from"./Authenticated.7db80fdf.js";import{_ as L}from"./Button.23a05acd.js";import be from"./ChannelOverview.c5ecb429.js";import ke from"./Create.8c45e882.js";import Ce from"./Form.2a811a13.js";import{_ as Te}from"./Paginator.7c2c62b7.js";import{_ as C,r as we}from"./SearchInput.bc3567b9.js";import{_ as w}from"./MultiSelect.2415f05c.js";import{_ as B,a as f}from"./TableData.b7faba11.js";import{_ as V}from"./TableHeadSort.124fba78.js";import{r as Se}from"./BackspaceIcon.2558c7fa.js";import{r as Ve}from"./ArrowDownTrayIcon.2cc683aa.js";import{r as Je}from"./PencilSquareIcon.5e700db8.js";import"./open-closed.13f31f1e.js";import"./use-resolve-button-type.add6567f.js";import"./RectangleStackIcon.71077489.js";import"./Modal.a8b67aa4.js";import"./CheckCircleIcon.7b2c8429.js";import"./ChevronDoubleUpIcon.fce11a1b.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.a2e98afe.js";import"./FormInput.b01b0517.js";import"./ArrowUturnLeftIcon.6dcf8b2c.js";import"./DatePicker.1526700d.js";import"./main.52257fc3.js";import"./ArrowUturnDownIcon.68150a41.js";function Be(d,S){return r(),c("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[t("path",{"fill-rule":"evenodd",d:"M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z","clip-rule":"evenodd"})])}const $e={class:"flex justify-center"},Ke={class:"pt-0.5 text-blue-600 hover:text-blue-800"},Le={key:0},De=t("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M19 9l-7 7-7-7"})],-1),Ne=[De],Oe={key:1},Fe=t("svg",{xmlns:"http://www.w3.org/2000/svg",class:"h-4 w-4",fill:"none",viewBox:"0 0 24 24",stroke:"currentColor","stroke-width":"2"},[t("path",{"stroke-linecap":"round","stroke-linejoin":"round",d:"M5 15l7-7 7 7"})],-1),Me=[Fe],A={__name:"SingleSortItem",props:{modelName:String,sortKey:String,sortBy:{type:Boolean,default:!0}},setup(d){return(S,o)=>(r(),c("div",$e,[t("a",{href:"#",class:"text-blue-600 hover:text-blue-800",onClick:o[0]||(o[0]=J=>S.$emit("sortTable",d.modelName))},[ve(S.$slots,"default"),t("div",Ke,[d.sortKey===d.modelName&&d.sortBy?(r(),c("span",Le,Ne)):m("",!0),d.sortKey===d.modelName&&!d.sortBy?(r(),c("span",Oe,Me)):m("",!0)])])]))}};const je=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),Ie={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Ue={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Pe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Ee=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Ae=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),Ge=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),He={key:2},qe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Re={key:3},Ye=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Ze=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),ze={key:4},Qe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Active? ",-1),We=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Xe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),et={key:5},tt=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),ot=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Location Type ",-1),nt={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},st={class:"mt-3"},at={class:"flex flex-col md:flex-row"},lt=t("span",null," Search ",-1),rt=t("span",null," Reset ",-1),it={class:"flex space-x-1"},dt={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},ut=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),ct=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),mt=[ut,ct],pt=t("span",null," Export Channels Excel ",-1),_t={class:"flex space-x-1"},yt={key:1,"aria-hidden":"true",class:"mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600",viewBox:"0 0 100 101",fill:"none",xmlns:"http://www.w3.org/2000/svg"},gt=t("path",{d:"M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z",fill:"currentColor"},null,-1),vt=t("path",{d:"M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z",fill:"currentFill"},null,-1),ft=[gt,vt],xt=t("span",null," Sync Next Delivery Date ",-1),ht={class:"flex flex-col space-y-1"},bt={class:"text-sm text-gray-700 leading-5"},kt={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ct=t("span",null,"Showing",-1),Tt={class:"font-medium"},wt=t("span",null,"to",-1),St={class:"font-medium"},Vt=t("span",null,"of",-1),Jt={class:"font-medium"},Bt=t("span",null,"results",-1),$t={class:"mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3"},Kt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Lt=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Total Sales (Last 30 days)",-1),Dt={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Nt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},Ot=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per VM (Last 30 days)",-1),Ft={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Mt={class:"overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow"},jt=t("dt",{class:"truncate text-sm font-medium text-gray-500"},"Avg per Day per VM (Last 30 days)",-1),It={class:"mt-1 text-2xl font-semibold tracking-normal text-gray-900"},Ut={class:"mt-6 flex flex-col"},Pt={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Et={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},At={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Gt={class:"bg-gray-100"},Ht={class:"divide-x divide-gray-200"},qt=t("br",null,null,-1),Rt=t("br",null,null,-1),Yt=t("br",null,null,-1),Zt=t("br",null,null,-1),zt=t("br",null,null,-1),Qt={class:"bg-white"},Wt={key:0},Xt={key:0},eo=["href"],to=t("br",null,null,-1),oo={key:1},no=t("br",null,null,-1),so={key:1},ao={class:"flex flex-col items-center space-y-1"},lo=["onClick"],ro=["onClick"],io=["onClick"],uo=["onClick"],co={class:"mt-1"},mo=["onClick"],po={class:"text-blue-600"},_o={class:"flex flex-col"},yo={class:"font-bold"},go=t("br",null,null,-1),vo=t("br",null,null,-1),fo=t("br",null,null,-1),xo=t("br",null,null,-1),ho=t("br",null,null,-1),bo={class:"flex flex-col space-y-1"},ko={class:"flex flex-col"},Co={class:"font-bold"},To={class:"flex flex-col"},wo={class:"font-bold"},So={key:0},Vo={class:"flex flex-col"},Jo=t("span",{class:"font-bold"}," Drop Sensor ",-1),Bo={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},$o={class:"flex flex-col"},Ko=t("span",{class:"font-bold"}," Fan Speed ",-1),Lo={class:"flex flex-col"},Do=t("span",{class:"font-bold"}," Door ",-1),No={class:"flex flex-col"},Oo=t("span",{class:"font-bold"}," Coin ",-1),Fo={key:4,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},Mo={class:"flex flex-col"},jo=t("span",{class:"font-bold"}," MQTT ",-1),Io={key:0},Uo=t("br",null,null,-1),Po={key:0},Eo=t("br",null,null,-1),Ao=t("br",null,null,-1),Go=t("br",null,null,-1),Ho={key:0},qo={key:1},Ro=t("br",null,null,-1),Yo=t("br",null,null,-1),Zo={key:0,class:"text-blue-600"},zo=t("br",null,null,-1),Qo={key:0},Wo={class:"flex justify-center space-x-1"},Xo=t("span",null," Edit ",-1),en={key:0},tn=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),on=[tn],$n={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,locationTypeOptions:Object,operatorOptions:Object,productOptions:Object,totals:[Array,Object],vends:Object,vendChannelErrors:Object},setup(d){const S=d,o=v({codes:"",channel_codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],locationType:"",operator:"",is_active:!0,is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",lastVisitedGreaterThan:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",balanceStockLessThan:"",remainingSkuLessThan:"",sortKey:"",sortBy:!0,numberPerPage:"",visited:!0}),J=v([]),le=v([]),ee=v([]),te=v([]),G=v([]),H=v([]),D=v(!1),N=v(!1),q=v([]),R=v([]),Y=v([]),O=v(!1),Z=v(!1),F=v(!1),z=v(""),M=v(),Q=v([]),$=K().props.auth.operatorCountry;K().props.auth.operatorRole;const b=K().props.auth.permissions,j=K().props.auth.roles,re=K().props.initBinded,oe=v(P().format("HH:mm:ss"));fe(()=>{o.value.visited=!0,Q.value=[{id:"errors_only",desc:"Errors Only"},...S.vendChannelErrors.data],R.value=[{id:50,value:50},{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=Q.value[0],o.value.numberPerPage=R.value[0],ee.value=S.categories.data.map(u=>({id:u.id,name:u.name})),te.value=S.categoryGroups.data.map(u=>({id:u.id,name:u.name})),J.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],le.value=[{id:"true",value:"Yes"},{id:"false",value:"No"}],H.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],G.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],q.value=[{id:"all",value:"All"},...S.locationTypeOptions.data.map(u=>({id:u.id,value:u.name}))],Y.value=[{id:"all",full_name:"All"},...S.operatorOptions.data.map(u=>({id:u.id,full_name:u.full_name}))],o.value.is_active=J.value[1],o.value.is_door_open=G.value[0],o.value.is_online=J.value[0],o.value.is_sensor=H.value[0],o.value.is_binded_customer=re&&(j[0]=="superadmin"||j[0]=="admin"||j[0]=="supervisor"||j[0]=="driver")?J.value[1]:J.value[0],o.value.locationType=q.value[0],o.value.operator=Y.value[0]});function ie(u){return u>=3e3?"text-green-700":u>=2e3&&u<3e3?"text-blue-700":u>=1500&&u<2e3?"text-gray-700":u>=1e3&&u<1500?"text-red-700":"text-gray-700 bg-red-300 px-1 rounded-sm"}function de(u){M.value=u,O.value=!0}function ue(){O.value=!1}function ce(){Z.value=!1}function me(u){z.value="edit",M.value=u,F.value=!0}function pe(){F.value=!1}function x(){X.get("/vends",{...o.value,categories:o.value.categories.map(u=>u.id),categoryGroups:o.value.categoryGroups.map(u=>u.id),errors:o.value.errors.map(u=>u.id),location_type_id:o.value.locationType.id,operator_id:o.value.operator.id,is_active:o.value.is_active.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:u=>{oe.value=P().format("HH:mm:ss")}})}function _e(){N.value=!0,ae({method:"get",url:"/customers/sync-next-delivery-date"}).then(u=>{}).catch(u=>{}).finally(()=>{N.value=!1})}function I(u,n){X.get("/vends/"+u+"/temp/"+n)}function ye(){X.get("/vends")}function h(u,n=!1){o.value.sortBy=!o.value.sortBy,n&&o.value.sortKey!=u&&(o.value.sortBy=!o.value.sortBy),o.value.sortKey=u,x()}function ge(){D.value=!0,ae({method:"get",url:"/vends/channels/excel",params:{...o.value,categories:o.value.categories.map(u=>u.id),categoryGroups:o.value.categoryGroups.map(u=>u.id),errors:o.value.errors.map(u=>u.id),location_type_id:o.value.locationType.id,operator_id:o.value.operator.id,is_active:o.value.is_active.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id},responseType:"blob"}).then(u=>{fileDownload(u.data,"Vending_Channels_"+P().format("YYMMDDhhmmss")+".xlsx")}).catch(u=>{console.log(u)}).finally(()=>{D.value=!1})}return(u,n)=>(r(),c(E,null,[a(_(xe),{title:"Vending Machines"}),a(he,null,{header:i(()=>[je]),default:i(()=>{var ne,se;return[t("div",Ie,[t("div",Ue,[t("div",Pe,[a(C,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":n[0]||(n[0]=e=>o.value.codes=e),onKeyup:n[1]||(n[1]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Vend ID "),Ee]),_:1},8,["modelValue"]),a(C,{placeholderStr:"Channel ID",modelValue:o.value.channel_codes,"onUpdate:modelValue":n[2]||(n[2]=e=>o.value.channel_codes=e),onKeyup:n[3]||(n[3]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Channel ID "),Ae]),_:1},8,["modelValue"]),a(C,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":n[4]||(n[4]=e=>o.value.serialNum=e),onKeyup:n[5]||(n[5]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Serial Num ")]),_:1},8,["modelValue"]),a(C,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":n[6]||(n[6]=e=>o.value.tempHigherThan=e),onKeyup:n[7]||(n[7]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Temp >> ")]),_:1},8,["modelValue"]),a(C,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":n[8]||(n[8]=e=>o.value.tempDeltaHigherThan=e),onKeyup:n[9]||(n[9]=k(e=>x(),["enter"]))},{default:i(()=>[l(" T1-T2 Delta >> ")]),_:1},8,["modelValue"]),t("div",null,[Ge,a(w,{modelValue:o.value.errors,"onUpdate:modelValue":n[10]||(n[10]=e=>o.value.errors=e),options:Q.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),_(b).includes("admin-access vends")?(r(),T(C,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":n[11]||(n[11]=e=>o.value.customer_code=e),onKeyup:n[12]||(n[12]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Cust ID ")]),_:1},8,["modelValue"])):m("",!0),_(b).includes("admin-access vends")?(r(),T(C,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":n[13]||(n[13]=e=>o.value.customer_name=e),onKeyup:n[14]||(n[14]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Cust Name ")]),_:1},8,["modelValue"])):m("",!0),_(b).includes("admin-access vends")?(r(),c("div",He,[qe,a(w,{modelValue:o.value.categories,"onUpdate:modelValue":n[15]||(n[15]=e=>o.value.categories=e),options:ee.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),_(b).includes("admin-access vends")?(r(),c("div",Re,[Ye,a(w,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":n[16]||(n[16]=e=>o.value.categoryGroups=e),options:te.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[Ze,a(w,{modelValue:o.value.is_online,"onUpdate:modelValue":n[17]||(n[17]=e=>o.value.is_online=e),options:J.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),_(b).includes("admin-access vends")?(r(),c("div",ze,[Qe,a(w,{modelValue:o.value.is_active,"onUpdate:modelValue":n[18]||(n[18]=e=>o.value.is_active=e),options:J.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[We,a(w,{modelValue:o.value.is_sensor,"onUpdate:modelValue":n[19]||(n[19]=e=>o.value.is_sensor=e),options:H.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Xe,a(w,{modelValue:o.value.is_door_open,"onUpdate:modelValue":n[20]||(n[20]=e=>o.value.is_door_open=e),options:G.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),a(C,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":n[21]||(n[21]=e=>o.value.fanSpeedLowerThan=e),onKeyup:n[22]||(n[22]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Fan Speed << ")]),_:1},8,["modelValue"]),_(b).includes("admin-access vends")?(r(),c("div",et,[tt,a(w,{modelValue:o.value.operator,"onUpdate:modelValue":n[23]||(n[23]=e=>o.value.operator=e),options:Y.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),t("div",null,[ot,a(w,{modelValue:o.value.locationType,"onUpdate:modelValue":n[24]||(n[24]=e=>o.value.locationType=e),options:q.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),a(C,{placeholderStr:"How many Day(s)",modelValue:o.value.lastVisitedGreaterThan,"onUpdate:modelValue":n[25]||(n[25]=e=>o.value.lastVisitedGreaterThan=e),onKeyup:n[26]||(n[26]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Last Visited Day >> ")]),_:1},8,["modelValue"]),a(C,{placeholderStr:"Balance Stock Less Than",modelValue:o.value.balanceStockLessThan,"onUpdate:modelValue":n[27]||(n[27]=e=>o.value.balanceStockLessThan=e),onKeyup:n[28]||(n[28]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Balance Stock(%) << ")]),_:1},8,["modelValue"]),a(C,{placeholderStr:"Remaining SKU Less Than",modelValue:o.value.remainingSkuLessThan,"onUpdate:modelValue":n[29]||(n[29]=e=>o.value.remainingSkuLessThan=e),onKeyup:n[30]||(n[30]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Remaining SKU(%) << ")]),_:1},8,["modelValue"]),_(b).includes("admin-access vends")?(r(),T(C,{key:6,placeholderStr:"Firmware Ver",modelValue:o.value.virtual_firmware_ver,"onUpdate:modelValue":n[31]||(n[31]=e=>o.value.virtual_firmware_ver=e),onKeyup:n[32]||(n[32]=k(e=>x(),["enter"]))},{default:i(()=>[l(" Firmware Ver ")]),_:1},8,["modelValue"])):m("",!0)]),t("div",nt,[t("div",st,[t("div",at,[a(L,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[33]||(n[33]=e=>x())},{default:i(()=>[a(_(we),{class:"h-4 w-4","aria-hidden":"true"}),lt]),_:1}),a(L,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[34]||(n[34]=e=>ye())},{default:i(()=>[a(_(Se),{class:"h-4 w-4","aria-hidden":"true"}),rt]),_:1}),_(b).includes("export excel")?(r(),T(L,{key:0,type:"button",class:"rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100",onClick:n[35]||(n[35]=e=>ge())},{default:i(()=>[t("div",it,[t("div",null,[D.value?m("",!0):(r(),T(_(Ve),{key:0,class:"h-4 w-4","aria-hidden":"true"})),D.value?(r(),c("svg",dt,mt)):m("",!0)]),pt])]),_:1})):m("",!0),_(b).includes("admin-access vends")?(r(),T(L,{key:1,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-blue-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[36]||(n[36]=e=>_e())},{default:i(()=>[t("div",_t,[t("div",null,[N.value?m("",!0):(r(),T(_(Be),{key:0,class:"h-4 w-4","aria-hidden":"true"})),N.value?(r(),c("svg",yt,ft)):m("",!0)]),xt])]),_:1})):m("",!0)])]),t("div",ht,[t("span",bt,[t("p",null,"Last loaded: "+s(oe.value),1)]),t("p",kt,[Ct,t("span",Tt,s((ne=d.vends.meta.from)!=null?ne:0),1),wt,t("span",St,s((se=d.vends.meta.to)!=null?se:0),1),Vt,t("span",Jt,s(d.vends.meta.total),1),Bt]),a(w,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":n[37]||(n[37]=e=>o.value.numberPerPage=e),options:R.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:x},null,8,["modelValue","options"])])]),t("dl",$t,[t("div",Kt,[Lt,t("dd",Dt,s(d.totals.thirtyDays.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",Nt,[Ot,t("dd",Ft,s((d.totals.thirtyDays/d.vends.meta.to?d.totals.thirtyDays/d.vends.meta.to:0).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)]),t("div",Mt,[jt,t("dd",It,s((d.totals.thirthyDaysAvg/d.vends.meta.to?d.totals.thirthyDaysAvg/d.vends.meta.to:0).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),1)])])]),t("div",Ut,[t("div",Pt,[t("div",Et,[t("table",At,[t("thead",Gt,[t("tr",Ht,[a(B,null,{default:i(()=>[l(" # ")]),_:1}),a(V,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[38]||(n[38]=e=>h("vends.code"))},{default:i(()=>[l(" ID ")]),_:1},8,["sortKey","sortBy"]),a(B,null,{default:i(()=>[l(" Name ")]),_:1}),a(V,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[39]||(n[39]=e=>h("temp"))},{default:i(()=>[l(" T1\u2103(freezer) "),qt,l(" T2\u2103(evap) "),Rt,l(" \u0394T1-T2 ")]),_:1},8,["sortKey","sortBy"]),a(B,null,{default:i(()=>[l(" Inventory Status "),Yt,l(" (#Channel, Sold, Balance/Capacity) ")]),_:1}),a(B,null,{default:i(()=>[l(" Errors ")]),_:1}),a(V,{modelName:"balance_percent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[40]||(n[40]=e=>h("balance_percent"))},{default:i(()=>[l(" Balance Stock ")]),_:1},8,["sortKey","sortBy"]),a(V,{modelName:"out_of_stock_sku_percent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[41]||(n[41]=e=>h("out_of_stock_sku_percent"))},{default:i(()=>[l(" Remaining SKU# ")]),_:1},8,["sortKey","sortBy"]),a(B,null,{default:i(()=>[l(" Sales(qty) "),a(A,{modelName:"vend_transaction_totals_json->today_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[42]||(n[42]=e=>h("vend_transaction_totals_json->today_amount",!0))},{default:i(()=>[l(" Today ")]),_:1},8,["sortKey","sortBy"]),a(A,{modelName:"vend_transaction_totals_json->yesterday_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[43]||(n[43]=e=>h("vend_transaction_totals_json->yesterday_amount",!0))},{default:i(()=>[l(" Y'day ")]),_:1},8,["sortKey","sortBy"]),a(A,{modelName:"vend_transaction_totals_json->seven_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[44]||(n[44]=e=>h("vend_transaction_totals_json->seven_days_amount",!0))},{default:i(()=>[l(" Last7d ")]),_:1},8,["sortKey","sortBy"]),a(A,{modelName:"vend_transaction_totals_json->thirty_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[45]||(n[45]=e=>h("vend_transaction_totals_json->thirty_days_amount",!0))},{default:i(()=>[l(" Last30d ")]),_:1},8,["sortKey","sortBy"])]),_:1}),a(B,null,{default:i(()=>[l(" Status ")]),_:1}),a(V,{modelName:"last_invoice_date",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[46]||(n[46]=e=>h("last_invoice_date"))},{default:i(()=>[l(" Last Visited ")]),_:1},8,["sortKey","sortBy"]),a(V,{modelName:"next_invoice_date",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[47]||(n[47]=e=>h("next_invoice_date"))},{default:i(()=>[l(" Next Planned Visit ")]),_:1},8,["sortKey","sortBy"]),a(V,{modelName:"amount_average_day",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[48]||(n[48]=e=>h("amount_average_day",!0))},{default:i(()=>[l(" Lifetime Sales,"),Zt,l(" Begin Date, "),zt,l(" Avg Sales/ Day ")]),_:1},8,["sortKey","sortBy"]),a(V,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[49]||(n[49]=e=>h("postcode"))},{default:i(()=>[l(" Postcode ")]),_:1},8,["sortKey","sortBy"]),a(B,null,{default:i(()=>[l(" Firmware Ver ")]),_:1}),a(V,{modelName:"location_type_name",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[50]||(n[50]=e=>h("location_type_name"))},{default:i(()=>[l(" Location ")]),_:1},8,["sortKey","sortBy"]),a(B)])]),t("tbody",Qt,[(r(!0),c(E,null,W(d.vends.data,(e,g)=>(r(),c("tr",{key:g,class:"divide-x divide-gray-200"},[a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[l(s(d.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[l(s(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:i(()=>[e.customer_code?(r(),c("span",Wt,[_(b).includes("admin-access vends")?(r(),c("span",Xt,[t("a",{class:"text-blue-700",target:"_blank",href:"//admin.happyice.com.sg/person/vend-code/"+e.code},[l(s(e.customer_code)+" ",1),to,l(" "+s(e.customer_name),1)],8,eo)])):(r(),c("span",oo,[l(s(e.customer_code)+" ",1),no,l(" "+s(e.customer_name),1)]))])):(r(),c("span",so,s(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[t("div",ao,[e.temp_updated_at?(r(),c("button",{key:0,type:"button",class:y(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>I(e.id,1)},s(e.is_temp_error?"Error":e.temp),11,lo)):m("",!0),e.parameterJson&&"t2"in e.parameterJson?(r(),c("button",{key:1,type:"button",class:y(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>I(e.id,2)},s(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,ro)):m("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=d.constTempError?(r(),c("button",{key:2,type:"button",class:y(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>I(e.id,3)},s(e.parameterJson.t3==d.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,io)):m("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=d.constTempError?(r(),c("button",{key:3,type:"button",class:y(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:p=>I(e.id,4)},s(e.parameterJson.t4==d.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,uo)):m("",!0),t("span",co,s(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=d.constTempError&&!e.is_temp_error?(r(),c("span",{key:4,class:y(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},s((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-left"},{default:i(()=>[e.vendChannelsJson?(r(),c("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:p=>de(e)},[(r(!0),c(E,null,W(e.vendChannelsJson,(p,U)=>(r(),c("li",{class:y(["quick-look",[U>0&&String(p.code)[0]!==String(e.vendChannelsJson[U-1].code)[0]?"col-start-1":""]])},[t("span",{class:y([U>0&&String(p.code)[0]!==String(e.vendChannelsJson[U-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+s(p.code)+", ",1),t("span",po,s(p.capacity-p.qty)+", ",1),t("span",{class:y([p.qty<=2?"text-red-700":"text-green-700"])},s(p.qty)+"/"+s(p.capacity),3)],2)],2))),256))],8,mo)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[(r(!0),c(E,null,W(e.vendChannelErrorLogsJson,p=>(r(),c("span",{class:y(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[p.vendChannelError?p.vendChannelError.code==4||p.vendChannelError.code==5||p.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":p.vend_channel.code==4||p.vend_channel.code==5||p.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",_o,[t("div",null,[l(" #"+s(p.vendChannel?p.vendChannel.code:p.vend_channel.code)+", ",1),t("span",yo," ("+s(p.vendChannelError?p.vendChannelError.code:p.vend_channel_error.code)+") ",1)]),t("div",null,s(p.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[e.vendChannelTotalsJson?(r(),c("span",{key:0,class:y([e.balance_percent<=20?"text-red-700":e.balance_percent>50?"text-green-700":"text-blue-700"])},[l(s(e.vendChannelTotalsJson.qty)+"/ "+s(e.vendChannelTotalsJson.capacity)+" ",1),go,l(" ("+s(e.balance_percent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[e.vendChannelTotalsJson?(r(),c("span",{key:0,class:y([100-e.out_of_stock_sku_percent<=40?"text-red-700":100-e.out_of_stock_sku_percent>70?"text-green-700":"text-blue-700"])},[l(s(e.vendChannelTotalsJson.count-e.vendChannelTotalsJson.outOfStockSku)+"/ "+s(e.vendChannelTotalsJson.count)+" ",1),vo,l(" ("+s(100-e.out_of_stock_sku_percent)+"%) ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[e.vendTransactionTotalsJson&&"today_amount"in e.vendTransactionTotalsJson?(r(),c("span",{key:0,class:y([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},s(_($).currency_symbol)+s((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+s(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)):m("",!0),e.vendTransactionTotalsJson&&"yesterday_amount"in e.vendTransactionTotalsJson?(r(),c("span",{key:1,class:y([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[fo,l(" "+s(_($).currency_symbol)+s((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+s(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),e.vendTransactionTotalsJson&&"seven_days_amount"in e.vendTransactionTotalsJson?(r(),c("span",{key:2,class:y([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},[xo,l(" "+s(_($).currency_symbol)+s((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+s(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0),e.vendTransactionTotalsJson&&"thirty_days_amount"in e.vendTransactionTotalsJson?(r(),c("span",{key:3,class:y([e.vendTransactionTotalsJson.thirty_days_amount/100>1e3?"text-green-700":"text-red-700"])},[ho,l(" "+s(_($).currency_symbol)+s((e.vendTransactionTotalsJson.thirty_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+s(e.vendTransactionTotalsJson.thirty_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1)],2)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[t("div",bo,[t("div",{class:y(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_active?"bg-green-200":"bg-red-200"]])},[t("div",ko,[t("span",Co,s(e.is_active?"Active":"Inactive"),1)])],2),t("div",{class:y(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",To,[t("span",wo,s(e.is_online?"Online":"Offline"),1),e.last_updated_at?(r(),c("span",So,s(e.last_updated_at),1)):m("",!0)])],2),e.parameterJson?(r(),c("div",{key:0,class:y(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",Vo,[Jo,t("span",null,s(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):m("",!0),e.parameterJson&&"fan"in e.parameterJson?(r(),c("div",Bo,[t("div",$o,[Ko,t("span",null,s(e.parameterJson.fan),1)])])):m("",!0),e.parameterJson&&e.parameterJson.door?(r(),c("div",{key:2,class:y(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",Lo,[Do,t("span",null,s(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):m("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(r(),c("div",{key:3,class:y(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",No,[Oo,t("span",null,s((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):m("",!0),e.is_mqtt?(r(),c("div",Fo,[t("div",Mo,[jo,e.mqtt_updated_at?(r(),c("span",Io,[Uo,l(" "+s(e.mqtt_updated_at),1)])):m("",!0)])])):m("",!0)])]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[e.cms_invoice_history&&"last_delivery_driver"in e.cms_invoice_history?(r(),c("span",Po,[l(s(e.cms_invoice_history.last_delivery_driver)+" ",1),Eo])):m("",!0),t("span",null,[l(s(e.last_invoice_date)+" ",1),Ao,l(" "+s(e.last_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[t("span",null,[l(s(e.next_invoice_date)+" ",1),Go,l(" "+s(e.next_invoice_diff),1)])]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[e.vendTransactionTotalsJson&&"vend_records_amount_latest"in e.vendTransactionTotalsJson?(r(),c("span",Ho,s(_($).currency_symbol)+s((e.vendTransactionTotalsJson.vend_records_amount_latest/100).toLocaleString(void 0,{minimumFractionDigits:0,maximumFractionDigits:0})),1)):m("",!0),e.begin_date?(r(),c("span",qo,[Ro,l(" "+s(e.begin_date_short),1)])):m("",!0),Yo,e.vendTransactionTotalsJson&&"vend_records_amount_average_day"in e.vendTransactionTotalsJson?(r(),c("span",{key:2,class:y(ie(e.vendTransactionTotalsJson.vend_records_amount_average_day))},s(_($).currency_symbol)+s((e.vendTransactionTotalsJson.vend_records_amount_average_day/100).toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2})),3)):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[l(s(e.postcode),1)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[l(s(e.parameterJson&&e.parameterJson.Ver?e.parameterJson.Ver.toString(16):null)+" ",1),e.apkVerJson&&"apkver"in e.apkVerJson?(r(),c("span",Zo,[zo,l("Apk: "+s(e.apkVerJson.apkver)+" ",1),e.apkVerJson&&"buildtime"in e.apkVerJson?(r(),c("span",Qo,s(_(P)(new Date(e.apkVerJson.buildtime)).format("YYMMDD HH:mm:ss")),1)):m("",!0)])):m("",!0)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[l(s(e.location_type_name),1)]),_:2},1032,["currentIndex","totalLength"]),a(f,{currentIndex:g,totalLength:d.vends.length,inputClass:"text-center"},{default:i(()=>[t("div",Wo,[a(L,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:p=>me(e)},{default:i(()=>[a(_(Je),{class:"w-4 h-4"}),Xo]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?m("",!0):(r(),c("tr",en,on))])])]),d.vends.data.length?(r(),T(Te,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):m("",!0)])])]),O.value?(r(),T(be,{key:0,productOptions:d.productOptions,vend:M.value,showModal:O.value,onModalClose:ue},null,8,["productOptions","vend","showModal"])):m("",!0),F.value?(r(),T(Ce,{key:1,vend:M.value,type:z.value,showModal:F.value,permissions:_(b),onModalClose:pe},null,8,["vend","type","showModal","permissions"])):m("",!0),Z.value?(r(),T(ke,{key:2,showModal:Z.value,permissions:_(b),type:z.value,onModalClose:ce},null,8,["showModal","permissions","type"])):m("",!0)]}),_:1})],64))}};export{$n as default};
