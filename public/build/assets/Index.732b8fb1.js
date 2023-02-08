import{_ as ee}from"./Authenticated.c98d061d.js";import{_ as F}from"./Button.b98b4b5d.js";import te from"./ChannelOverview.fe9eca5e.js";import oe from"./Form.3c303700.js";import{_ as C,r as ne,T as x,a as se,b as f}from"./TableData.1fc6330c.js";import{_ as y}from"./MultiSelect.301c702e.js";import{_ as S}from"./TableHeadSort.13d34b9c.js";import{i as h,l as le,j as ae,o as d,g as c,a as s,b,w as r,F as $,H as re,d as t,c as T,p,t as l,m as U,f as a,J as H,n as _}from"./app.8a6e3b3a.js";import{r as ie}from"./BackspaceIcon.59bee29f.js";import{r as de}from"./PencilSquareIcon.2477f20e.js";import"./open-closed.2ea4fa41.js";import"./use-resolve-button-type.402707f6.js";import"./RectangleStackIcon.f4c1ca47.js";import"./Modal.6f52e633.js";import"./FormInput.71e7172a.js";import"./ArrowUturnLeftIcon.8dcf83ff.js";import"./CheckCircleIcon.c40e5d53.js";import"./_plugin-vue_export-helper.cdc0426e.js";const ue=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),ce={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},me={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},pe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},_e=a(" Vend ID "),ge=t("span",{class:"text-[9px]"},' ("," for multiple) ',-1),fe=a(" Serial Num "),he=a(" Temp >> "),be=a(" t1-t2 Delta >> "),ye=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Channel Errors ",-1),xe=a(" Cust ID "),ve=a(" Cust Name "),ke={key:2},Ce=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),Se={key:3},Ve=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),Te=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),Je={key:4},Be=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),we=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Sensor Status ",-1),Pe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Door Open ",-1),Oe=a(" Fan Speed << "),Le={key:5},$e=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Operator ",-1),je={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Ne={class:"mt-3"},Ie={class:"flex space-x-1"},Ke=t("span",null," Search ",-1),De=t("span",null," Reset ",-1),Ee={class:"flex flex-col space-y-1"},Fe={class:"text-sm text-gray-700 leading-5"},Ue={class:"text-sm text-gray-700 leading-5 flex space-x-1"},He=t("span",null,"Showing",-1),Me={class:"font-medium"},Ge=t("span",null,"to",-1),Ae={class:"font-medium"},qe=t("span",null,"of",-1),Re={class:"font-medium"},Ye=t("span",null,"results",-1),ze={class:"mt-6 flex flex-col"},Qe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},We={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Xe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ze={class:"bg-gray-100"},et={class:"divide-x divide-gray-200"},tt=a(" # "),ot=a(" ID "),nt=a(" Name "),st=a(" Temp1(\u2103) "),lt=t("br",null,null,-1),at=a(" \u0394t1-t2 "),rt=a(" Inventory Status "),it=t("br",null,null,-1),dt=a(" (#Channel, Sales, Balance/Capacity) "),ut=a(" Errors "),ct=a(" Balance Stock "),mt=a(" Out of Stock SKU "),pt=a(" $ Sales (qty)"),_t=t("br",null,null,-1),gt=a(" Today "),ft=t("br",null,null,-1),ht=a(" Yesterday"),bt=t("br",null,null,-1),yt=a(" Last 7 Days "),xt=a(" Status "),vt=a(" Temp2 "),kt=t("br",null,null,-1),Ct=a(" (Evap)"),St=t("br",null,null,-1),Vt=a(" \u2103 "),Tt=a(" Postcode "),Jt=a(" Firmware Ver "),Bt=a(" Serial Num "),wt={class:"bg-white"},Pt={key:0},Ot=t("br",null,null,-1),Lt={key:1},$t={class:"flex flex-col items-center"},jt=["onClick"],Nt={class:"mt-1"},It=["onClick"],Kt={class:"text-blue-600"},Dt={class:"flex flex-col"},Et={class:"font-bold"},Ft=t("br",null,null,-1),Ut=t("br",null,null,-1),Ht=t("br",null,null,-1),Mt=t("br",null,null,-1),Gt={class:"flex flex-col space-y-1"},At={class:"flex flex-col"},qt={class:"font-bold"},Rt={key:0},Yt={class:"flex flex-col"},zt=t("span",{class:"font-bold"}," Drop Sensor ",-1),Qt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},Wt={class:"flex flex-col"},Xt=t("span",{class:"font-bold"}," Fan Speed ",-1),Zt={class:"flex flex-col"},eo=t("span",{class:"font-bold"}," Door ",-1),to={class:"flex flex-col"},oo=t("span",{class:"font-bold"}," Coin ",-1),no={class:"flex flex-col items-center space-y-1"},so=["onClick"],lo=["onClick"],ao=["onClick"],ro={class:"flex justify-center space-x-1"},io=t("span",null," Edit ",-1),uo={key:0},co=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),mo=[co],Oo={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,operatorOptions:Object,vends:Object,vendChannelErrors:Object},setup(i){const J=i,o=h({codes:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],errors:[],operator:"",is_binded_customer:"",tempHigherThan:"",tempDeltaHigherThan:"",vend_channel_error_id:"",is_online:"",is_sensor:"",is_door_open:"",fanSpeedLowerThan:"",sortKey:"",sortBy:!1,numberPerPage:""}),V=h([]),M=h([]),G=h([]),j=h([]),N=h([]),I=h([]),K=h([]),B=h(!1),w=h(!1),Y=h(""),P=h(),D=h([]),v=le().props.value.auth.operatorRole,A=h(moment().format("HH:mm:ss"));ae(()=>{D.value=[{id:"errors_only",desc:"Errors Only"},...J.vendChannelErrors.data],I.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],o.value.vend_channel_error_id=D.value[0],o.value.numberPerPage=I.value[0],M.value=J.categories.data.map(m=>({id:m.id,name:m.name})),G.value=J.categoryGroups.data.map(m=>({id:m.id,name:m.name})),V.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],N.value=[{id:"all",value:"All"},{id:"true",value:"Enabled"},{id:"false",value:"Disabled"}],j.value=[{id:"all",value:"All"},{id:"open",value:"Open"},{id:"close",value:"Close"}],K.value=[{id:"all",full_name:"All"},...J.operatorOptions.data.map(m=>({id:m.id,full_name:m.full_name}))],o.value.is_door_open=j.value[0],o.value.is_online=V.value[0],o.value.is_sensor=N.value[0],o.value.is_binded_customer=v.value?V.value[0]:V.value[1],o.value.operator=K.value[0]});function z(m){P.value=m,B.value=!0}function Q(){B.value=!1}function W(m){P.value=m,w.value=!0}function X(){w.value=!1}function E(){H.Inertia.get("/vends",{...o.value,categories:o.value.categories.map(m=>m.id),categoryGroups:o.value.categoryGroups.map(m=>m.id),errors:o.value.errors.map(m=>m.id),operator_id:o.value.operator.id,is_binded_customer:o.value.is_binded_customer.id,is_door_open:o.value.is_door_open.id,is_online:o.value.is_online.id,is_sensor:o.value.is_sensor.id,numberPerPage:o.value.numberPerPage.id},{preserveState:!0,replace:!0,onFinish:m=>{A.value=moment().format("HH:mm:ss")}})}function O(m,n){H.Inertia.get("/vends/"+m+"/temp/"+n)}function Z(){H.Inertia.get("/vends")}function k(m){o.value.sortKey=m,o.value.sortBy=!o.value.sortBy,E()}return(m,n)=>(d(),c($,null,[s(b(re),{title:"Vending Machines"}),s(ee,null,{header:r(()=>[ue]),default:r(()=>{var q,R;return[t("div",ce,[t("div",me,[t("div",pe,[s(C,{placeholderStr:"Vend ID",modelValue:o.value.codes,"onUpdate:modelValue":n[0]||(n[0]=e=>o.value.codes=e)},{default:r(()=>[_e,ge]),_:1},8,["modelValue"]),s(C,{placeholderStr:"Serial Num",modelValue:o.value.serialNum,"onUpdate:modelValue":n[1]||(n[1]=e=>o.value.serialNum=e)},{default:r(()=>[fe]),_:1},8,["modelValue"]),s(C,{placeholderStr:"Number",modelValue:o.value.tempHigherThan,"onUpdate:modelValue":n[2]||(n[2]=e=>o.value.tempHigherThan=e)},{default:r(()=>[he]),_:1},8,["modelValue"]),s(C,{placeholderStr:"Number",modelValue:o.value.tempDeltaHigherThan,"onUpdate:modelValue":n[3]||(n[3]=e=>o.value.tempDeltaHigherThan=e)},{default:r(()=>[be]),_:1},8,["modelValue"]),t("div",null,[ye,s(y,{modelValue:o.value.errors,"onUpdate:modelValue":n[4]||(n[4]=e=>o.value.errors=e),options:D.value,valueProp:"id",label:"desc",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),b(v)?p("",!0):(d(),T(C,{key:0,placeholderStr:"Cust ID",modelValue:o.value.customer_code,"onUpdate:modelValue":n[5]||(n[5]=e=>o.value.customer_code=e)},{default:r(()=>[xe]),_:1},8,["modelValue"])),b(v)?p("",!0):(d(),T(C,{key:1,placeholderStr:"Cust Name",modelValue:o.value.customer_name,"onUpdate:modelValue":n[6]||(n[6]=e=>o.value.customer_name=e)},{default:r(()=>[ve]),_:1},8,["modelValue"])),b(v)?p("",!0):(d(),c("div",ke,[Ce,s(y,{modelValue:o.value.categories,"onUpdate:modelValue":n[7]||(n[7]=e=>o.value.categories=e),options:M.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),b(v)?p("",!0):(d(),c("div",Se,[Ve,s(y,{modelValue:o.value.categoryGroups,"onUpdate:modelValue":n[8]||(n[8]=e=>o.value.categoryGroups=e),options:G.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),t("div",null,[Te,s(y,{modelValue:o.value.is_online,"onUpdate:modelValue":n[9]||(n[9]=e=>o.value.is_online=e),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),b(v)?p("",!0):(d(),c("div",Je,[Be,s(y,{modelValue:o.value.is_binded_customer,"onUpdate:modelValue":n[10]||(n[10]=e=>o.value.is_binded_customer=e),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])),t("div",null,[we,s(y,{modelValue:o.value.is_sensor,"onUpdate:modelValue":n[11]||(n[11]=e=>o.value.is_sensor=e),options:N.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[Pe,s(y,{modelValue:o.value.is_door_open,"onUpdate:modelValue":n[12]||(n[12]=e=>o.value.is_door_open=e),options:j.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(C,{placeholderStr:"Fan Speed",modelValue:o.value.fanSpeedLowerThan,"onUpdate:modelValue":n[13]||(n[13]=e=>o.value.fanSpeedLowerThan=e)},{default:r(()=>[Oe]),_:1},8,["modelValue"]),b(v)?p("",!0):(d(),c("div",Le,[$e,s(y,{modelValue:o.value.operator,"onUpdate:modelValue":n[14]||(n[14]=e=>o.value.operator=e),options:K.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]))]),t("div",je,[t("div",Ne,[t("div",Ie,[s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[15]||(n[15]=e=>E())},{default:r(()=>[s(b(ne),{class:"h-4 w-4","aria-hidden":"true"}),Ke]),_:1}),s(F,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[16]||(n[16]=e=>Z())},{default:r(()=>[s(b(ie),{class:"h-4 w-4","aria-hidden":"true"}),De]),_:1})])]),t("div",Ee,[t("span",Fe,[t("p",null,"Last loaded: "+l(A.value),1)]),t("p",Ue,[He,t("span",Me,l((q=i.vends.meta.from)!=null?q:0),1),Ge,t("span",Ae,l((R=i.vends.meta.to)!=null?R:0),1),qe,t("span",Re,l(i.vends.meta.total),1),Ye]),s(y,{modelValue:o.value.numberPerPage,"onUpdate:modelValue":n[17]||(n[17]=e=>o.value.numberPerPage=e),options:I.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:E},null,8,["modelValue","options"])])])]),t("div",ze,[t("div",Qe,[t("div",We,[t("table",Xe,[t("thead",Ze,[t("tr",et,[s(x,null,{default:r(()=>[tt]),_:1}),s(S,{modelName:"vends.code",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[18]||(n[18]=e=>k("vends.code"))},{default:r(()=>[ot]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:r(()=>[nt]),_:1}),s(S,{modelName:"temp",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[19]||(n[19]=e=>k("temp"))},{default:r(()=>[st,lt,at]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:r(()=>[rt,it,dt]),_:1}),s(x,null,{default:r(()=>[ut]),_:1}),s(S,{modelName:"vend_channel_totals_json->balancePercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[20]||(n[20]=e=>k("vend_channel_totals_json->balancePercent"))},{default:r(()=>[ct]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[21]||(n[21]=e=>k("vend_channel_totals_json->outOfStockSkuPercent"))},{default:r(()=>[mt]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"vend_transaction_totals_json->seven_days_amount",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[22]||(n[22]=e=>k("vend_transaction_totals_json->seven_days_amount"))},{default:r(()=>[pt,_t,gt,ft,ht,bt,yt]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:r(()=>[xt]),_:1}),s(S,{modelName:"parameter_json->t2",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[23]||(n[23]=e=>k("parameter_json->t2"))},{default:r(()=>[vt,kt,Ct,St,Vt]),_:1},8,["sortKey","sortBy"]),s(S,{modelName:"postcode",sortKey:o.value.sortKey,sortBy:o.value.sortBy,onSortTable:n[24]||(n[24]=e=>k("postcode"))},{default:r(()=>[Tt]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:r(()=>[Jt]),_:1}),s(x,null,{default:r(()=>[Bt]),_:1}),s(x)])]),t("tbody",wt,[(d(!0),c($,null,U(i.vends.data,(e,g)=>(d(),c("tr",{key:g,class:"divide-x divide-gray-200"},[s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(i.vends.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-left"},{default:r(()=>[e.latestVendBinding&&e.latestVendBinding.customer?(d(),c("span",Pt,[a(l(e.latestVendBinding.customer.code)+" ",1),Ot,a(" "+l(e.latestVendBinding.customer.name),1)])):(d(),c("span",Lt,l(e.name),1))]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",$t,[t("button",{type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,1)},l(e.is_temp_error?"Error":e.temp),11,jt),t("span",Nt,l(e.temp_updated_at),1),e.parameterJson&&e.parameterJson.t2&&e.parameterJson.t2!=i.constTempError&&!e.is_temp_error?(d(),c("span",{key:0,class:_(["mt-1",(e.temp-e.parameterJson.t2/10).toFixed(1)>=4?"text-red-700":"text-green-700"])},l((e.temp-e.parameterJson.t2/10).toFixed(1)),3)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-left"},{default:r(()=>[e.vendChannelsJson?(d(),c("ul",{key:0,class:"sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer",onClick:u=>z(e)},[(d(!0),c($,null,U(e.vendChannelsJson,(u,L)=>(d(),c("li",{class:_(["quick-look",[L>0&&String(u.code)[0]!==String(e.vendChannelsJson[L-1].code)[0]?"col-start-1":""]])},[t("span",{class:_([L>0&&String(u.code)[0]!==String(e.vendChannelsJson[L-1].code)[0]?"border-t-4 pt-1":""])},[t("span",null," #"+l(u.code)+", ",1),t("span",Kt,l(u.capacity-u.qty)+", ",1),t("span",{class:_([u.qty<=2?"text-red-700":"text-green-700"])},l(u.qty)+"/"+l(u.capacity),3)],2)],2))),256))],8,It)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[(d(!0),c($,null,U(e.vendChannelErrorLogsJson,u=>(d(),c("span",{class:_(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[u.vendChannelError?u.vendChannelError.code==4||u.vendChannelError.code==5||u.vendChannelError.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800":u.vend_channel.code==4||u.vend_channel.code==5||u.vend_channel.code==7?"bg-blue-100 text-blue-800":"bg-red-100 text-red-800"]])},[t("div",Dt,[t("div",null,[a(" #"+l(u.vendChannel?u.vendChannel.code:u.vend_channel.code)+", ",1),t("span",Et," ("+l(u.vendChannelError?u.vendChannelError.code:u.vend_channel_error.code)+") ",1)]),t("div",null,l(u.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(d(),c("span",{key:0,class:_([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[a(l(e.vendChannelTotalsJson.qty)+"/ "+l(e.vendChannelTotalsJson.capacity)+" ",1),Ft,a(" ("+l(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[e.vendChannelTotalsJson?(d(),c("span",{key:0,class:_([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[a(l(e.vendChannelTotalsJson.outOfStockSku)+"/ "+l(e.vendChannelTotalsJson.count)+" ",1),Ut,a(" ("+l(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):p("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("span",{class:_([e.vendTransactionTotalsJson.today_amount/100>=30?"text-green-700":"text-red-700"])},[a(l((e.vendTransactionTotalsJson.today_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.today_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1),Ht],2),t("span",{class:_([e.vendTransactionTotalsJson.yesterday_amount/100>=30?"text-green-700":"text-red-700"])},[a(l((e.vendTransactionTotalsJson.yesterday_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+" ("+l(e.vendTransactionTotalsJson.yesterday_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",1),Mt],2),t("span",{class:_([e.vendTransactionTotalsJson.seven_days_amount/100>200?"text-green-700":"text-red-700"])},l((e.vendTransactionTotalsJson.seven_days_amount/100).toLocaleString(void 0,{minimumFractionDigits:2}))+"("+l(e.vendTransactionTotalsJson.seven_days_count.toLocaleString(void 0,{minimumFractionDigits:0}))+") ",3)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",Gt,[t("div",{class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",At,[t("span",qt,l(e.is_online?"Online":"Offline"),1),e.last_updated_at?(d(),c("span",Rt,l(e.last_updated_at),1)):p("",!0)])],2),e.parameterJson?(d(),c("div",{key:0,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor%2==0?"bg-red-200":"bg-green-200"]])},[t("div",Yt,[zt,t("span",null,l(e.parameterJson.Sensor%2==0?"Disabled":"Enabled"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.fan?(d(),c("div",Qt,[t("div",Wt,[Xt,t("span",null,l(e.parameterJson.fan),1)])])):p("",!0),e.parameterJson&&e.parameterJson.door?(d(),c("div",{key:2,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",Zt,[eo,t("span",null,l(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):p("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(d(),c("div",{key:3,class:_(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",to,[oo,t("span",null,l((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",no,[e.parameterJson&&"t2"in e.parameterJson?(d(),c("button",{key:0,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t2==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,2)},l(e.parameterJson.t2==i.constTempError?"Error":e.parameterJson.t2/10)+"(t2) ",11,so)):p("",!0),e.parameterJson&&e.parameterJson.t3&&e.parameterJson.t3!=i.constTempError?(d(),c("button",{key:1,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t3==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,3)},l(e.parameterJson.t3==i.constTempError?"Error":e.parameterJson.t3/10)+"(t3) ",11,lo)):p("",!0),e.parameterJson&&e.parameterJson.t4&&e.parameterJson.t4!=i.constTempError?(d(),c("button",{key:2,type:"button",class:_(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.is_online?e.temp>-15||e.parameterJson.t4==i.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600":"bg-gray-300 active:bg-gray-500 hover:bg-gray-600"]]),onClick:u=>O(e.id,4)},l(e.parameterJson.t4==i.constTempError?"Error":e.parameterJson.t4/10)+"(t4) ",11,ao)):p("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[a(l(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),s(f,{currentIndex:g,totalLength:i.vends.length,inputClass:"text-center"},{default:r(()=>[t("div",ro,[s(F,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:u=>W(e)},{default:r(()=>[s(b(de),{class:"w-4 h-4"}),io]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),i.vends.data.length?p("",!0):(d(),c("tr",uo,mo))])])]),i.vends.data.length?(d(),T(se,{key:0,links:i.vends.links,meta:i.vends.meta},null,8,["links","meta"])):p("",!0)])])]),B.value?(d(),T(te,{key:0,vend:P.value,showModal:B.value,onModalClose:Q},null,8,["vend","showModal"])):p("",!0),w.value?(d(),T(oe,{key:1,vend:P.value,type:Y.value,showModal:w.value,onModalClose:X},null,8,["vend","type","showModal"])):p("",!0)]}),_:1})],64))}};export{Oo as default};