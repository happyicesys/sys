import{o as l,g as i,d as e,i as v,u as h,j as I,c as f,a as r,w as n,p as c,T as P,t as m,b as _,q as y,v as b,n as w,e as k,m as q,F as E,f as C}from"./app.c318dc46.js";import{_ as g}from"./Button.27f88839.js";import G from"./Uom.c5f83c55.js";import{_ as T,a as $,r as B}from"./Modal.6db78482.js";import{_ as H}from"./FormTextarea.45efcd35.js";import{_ as U}from"./MultiSelect.8217c9f4.js";import{r as J}from"./RectangleStackIcon.afa5836f.js";import{r as K}from"./ArrowUturnLeftIcon.f4e69ee0.js";import"./open-closed.4eec9221.js";function Q(d,p){return l(),i("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M2 4.75C2 3.784 2.784 3 3.75 3h4.836c.464 0 .909.184 1.237.513l1.414 1.414a.25.25 0 00.177.073h4.836c.966 0 1.75.784 1.75 1.75v8.5A1.75 1.75 0 0116.25 17H3.75A1.75 1.75 0 012 15.25V4.75zm10.25 7a.75.75 0 000-1.5h-4.5a.75.75 0 000 1.5h4.5z","clip-rule":"evenodd"})])}function R(d,p){return l(),i("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25v-8.5A1.75 1.75 0 0016.25 5h-4.836a.25.25 0 01-.177-.073L9.823 3.513A1.75 1.75 0 008.586 3H3.75zM10 8a.75.75 0 01.75.75v1.5h1.5a.75.75 0 010 1.5h-1.5v1.5a.75.75 0 01-1.5 0v-1.5h-1.5a.75.75 0 010-1.5h1.5v-1.5A.75.75 0 0110 8z","clip-rule":"evenodd"})])}function W(d,p){return l(),i("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z","clip-rule":"evenodd"})])}const X={class:"flex flex-col md:flex-row space-x-2"},Y={key:0,class:"text-gray-600"},Z={key:1},ee={key:2,class:"text-gray-600"},te=["onSubmit"],se={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},oe={class:"sm:col-span-6 pb-3"},ae={class:"mt-1 flex flex-col md:flex-row space-y-2 md:space-y-0 items-center"},le={class:"h-28 w-28 overflow-hidden rounded-full bg-gray-100"},re=["src"],ie=["value"],de={key:0,class:"text-sm text-red-600"},ne={class:"sm:col-span-2"},ce=C(" Code "),ue={class:"sm:col-span-4"},me=C(" Name "),pe={class:"sm:col-span-6"},_e=C(" Desc "),ve={class:"sm:col-span-3"},ge=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Category ",-1),xe={key:0,class:"text-sm text-red-600"},he={class:"sm:col-span-3"},fe=e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Group ",-1),ye={key:0,class:"text-sm text-red-600"},be={class:"sm:col-span-6 pt-2"},we={class:"flex md:justify-between flex-col space-y-3 md:flex-row md:space-y-0"},ke={class:"relative flex items-start"},Ce={class:"flex h-5 items-center"},Ve=e("div",{class:"ml-3 text-sm"},[e("label",{for:"candidates",class:"font-medium text-gray-700"},"Is Inventory?")],-1),Me={class:"relative flex items-start"},Se={class:"flex h-5 items-center"},$e=["disabled"],Be={class:"ml-3 text-sm"},Ue={class:"relative flex items-start"},je={class:"flex h-5 items-center"},Oe=["disabled"],Ae={class:"ml-3 text-sm"},Ne={class:"sm:col-span-6"},ze={class:"mt-8 flex flex-col"},De={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8"},Fe={class:"inline-block min-w-full py-2 align-middle md:px-6 lg:px-8"},Le={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},Ie={class:"min-w-full divide-y divide-gray-300"},Pe={class:"bg-gray-50"},qe=e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # ",-1),Ee=e("th",{scope:"col",class:"py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 sm:pl-6"}," UOM Name ",-1),Ge=e("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Value ",-1),Te={scope:"col",colspan:"2",class:"px-3 py-3.5 text-right text-sm font-semibold text-gray-900"},He=e("span",null," New UOM ",-1),Je={class:"divide-y divide-gray-200 bg-white"},Ke={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Qe={class:"whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center"},Re={class:"whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center"},We={class:"whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center"},Xe={class:"flex flex-col space-y-1 justify-center"},Ye={key:0,class:"inline-flex items-center rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800"},Ze={key:0,class:"inline-flex items-center rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800"},et={class:"whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center"},tt={class:"sm:col-span-6"},st={class:"flex space-x-1 mt-5 pt-5 justify-end"},ot=e("span",null," Back ",-1),at={key:0,class:"flex space-x-1 items-center"},lt=e("span",null," Deactivate ",-1),rt={key:1,class:"flex space-x-1 items-center"},it=e("span",null," Activate ",-1),dt=e("span",null," Save ",-1),ft={__name:"Form",props:{categories:Object,categoryGroups:Object,product:Object,uoms:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:p}){const u=d,V=v([]),M=v([]),x=v(!1),j=v([]),O=v(u.product.productUoms),t=v(h(S()));I(()=>{t.value=u.product?h(u.product):h(S()),V.value=u.categories.data.map(a=>({id:a.id,name:a.name})),M.value=u.categoryGroups.data.map(a=>({id:a.id,name:a.name})),j.value=u.uoms.data.map(a=>({id:a.id,name:a.name}))});function S(){return{code:"",desc:"",name:"",thumbnail:"",is_inventory:"",is_commission:"",is_supermarket_fee:"",category_id:"",category_group_id:""}}function A(){t.value.clearErrors(),u.type==="create"&&t.value.transform(a=>({...a,category_id:a.category_id.id,category_group_id:a.category_group_id.id})).post("/products/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),u.type==="update"&&t.value.transform(a=>({...a,category_id:a.category_id.id,category_group_id:a.category_group_id.id})).post("/products/"+t.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}function N(){t.value.post("/products/"+t.value.id+"/toggle-activate-deactivate",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}function z(a){t.value.delete("/products/product-uoms/"+a.id,{onSuccess:()=>{p("modalClose")},preserveState:!0,resetOnSuccess:!0,replace:!0})}function D(){x.value=!0}function F(){x.value=!1}return(a,o)=>(l(),f(P,{to:"body"},[r(T,{open:d.showModal,onModalClose:o[10]||(o[10]=s=>a.$emit("modalClose"))},{header:n(()=>[e("div",X,[d.product?(l(),i("span",Y," Editing ")):c("",!0),u.product?(l(),i("span",Z,m(d.product.name),1)):(l(),i("span",ee," Create New Product "))])]),default:n(()=>[e("form",{onSubmit:k(A,["prevent"]),id:"submit"},[e("div",se,[e("div",oe,[e("div",ae,[e("span",le,[d.product&&d.product.thumbnail?(l(),i("img",{key:0,class:"h-28 w-28 rounded-full border",src:d.product.thumbnail.full_url,alt:""},null,8,re)):c("",!0),r(_(J),{class:"h-28 w-28 text-gray-300"})]),e("input",{type:"file",onInput:o[0]||(o[0]=s=>t.value.thumbnail=s.target.files[0]),class:"ml-5 rounded-md border border-gray-300 bg-white py-2 px-3 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},null,32),t.value.progress?(l(),i("progress",{key:0,value:t.value.progress.percentage,max:"100"},m(t.value.progress.percentage)+"% ",9,ie)):c("",!0)]),t.value.errors.thumbnail?(l(),i("div",de,m(t.value.errors.thumbnail),1)):c("",!0)]),e("div",ne,[r($,{modelValue:t.value.code,"onUpdate:modelValue":o[1]||(o[1]=s=>t.value.code=s),error:t.value.errors.code,required:"true"},{default:n(()=>[ce]),_:1},8,["modelValue","error"])]),e("div",ue,[r($,{modelValue:t.value.name,"onUpdate:modelValue":o[2]||(o[2]=s=>t.value.name=s),error:t.value.errors.name,required:"true"},{default:n(()=>[me]),_:1},8,["modelValue","error"])]),e("div",pe,[r(H,{modelValue:t.value.desc,"onUpdate:modelValue":o[3]||(o[3]=s=>t.value.desc=s),error:t.value.errors.desc},{default:n(()=>[_e]),_:1},8,["modelValue","error"])]),e("div",ve,[ge,r(U,{modelValue:t.value.category_id,"onUpdate:modelValue":o[4]||(o[4]=s=>t.value.category_id=s),options:V.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.category_id?(l(),i("div",xe,m(t.value.errors.category_id),1)):c("",!0)]),e("div",he,[fe,r(U,{modelValue:t.value.category_group_id,"onUpdate:modelValue":o[5]||(o[5]=s=>t.value.category_group_id=s),options:M.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),t.value.errors.category_group_id?(l(),i("div",ye,m(t.value.errors.category_group_id),1)):c("",!0)]),e("div",be,[e("div",we,[e("div",ke,[e("div",Ce,[y(e("input",{id:"candidates","onUpdate:modelValue":o[6]||(o[6]=s=>t.value.is_inventory=s),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75"},null,512),[[b,t.value.is_inventory]])]),Ve]),e("div",Me,[e("div",Se,[y(e("input",{id:"candidates","onUpdate:modelValue":o[7]||(o[7]=s=>t.value.is_commission=s),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75",disabled:t.value.is_inventory},null,8,$e),[[b,t.value.is_commission]])]),e("div",Be,[e("label",{for:"candidates",class:w(["font-medium text-gray-700",[t.value.is_inventory?"text-gray-400":""]])},"Is Commission?",2)])]),e("div",Ue,[e("div",je,[y(e("input",{id:"candidates","onUpdate:modelValue":o[8]||(o[8]=s=>t.value.is_supermarket_fee=s),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75",disabled:t.value.is_inventory},null,8,Oe),[[b,t.value.is_supermarket_fee]])]),e("div",Ae,[e("label",{for:"candidates",class:w(["font-medium text-gray-700",[t.value.is_inventory?"text-gray-400":""]])},"Is Supermarket Fee?",2)])])])]),e("div",Ne,[e("div",ze,[e("div",De,[e("div",Fe,[e("div",Le,[e("table",Ie,[e("thead",Pe,[e("tr",null,[qe,Ee,Ge,e("th",Te,[r(g,{type:"button",onClick:k(D,["prevent"]),class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1 px-3 py-1"},{default:n(()=>[r(_(B),{class:"w-4 h-4"}),He]),_:1},8,["onClick"])])])]),e("tbody",Je,[(l(!0),i(E,null,q(O.value,(s,L)=>(l(),i("tr",{key:s.id},[e("td",Ke,m(L+1),1),e("td",Qe,m(s.uom.name),1),e("td",Re,m(s.value),1),e("td",We,[e("div",Xe,[e("div",null,[s.is_base_uom?(l(),i("span",Ye," base_uom ")):c("",!0)]),e("div",null,[s.is_transaction_uom?(l(),i("span",Ze," transacted_uom ")):c("",!0)])])]),e("td",et,[r(g,{type:"button",class:"bg-red-300 hover:bg-red-400 px-2 py-2 text-xs text-red-800 flex space-x-1",onClick:nt=>z(s)},{default:n(()=>[r(_(W),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])]))),128))])])])])])])])]),e("div",tt,[e("div",st,[r(g,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[9]||(o[9]=k(s=>a.$emit("modalClose"),["prevent"])),form:"submit"},{default:n(()=>[r(_(K),{class:"w-4 h-4"}),ot]),_:1}),t.value.id?(l(),f(g,{key:0,type:"button",onClick:N,class:w(["text-white",[t.value.is_active?"bg-red-500 hover:bg-red-600":"bg-green-500 hover:bg-green-600"]])},{default:n(()=>[e("div",null,[t.value.is_active?(l(),i("span",at,[r(_(Q),{class:"w-4 h-4"}),lt])):(l(),i("span",rt,[r(_(R),{class:"w-4 h-4"}),it]))])]),_:1},8,["class"])):c("",!0),r(g,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:n(()=>[r(_(B),{class:"w-4 h-4"}),dt]),_:1})])])],40,te)]),_:1},8,["open"]),x.value?(l(),f(G,{key:0,product:d.product,uoms:d.uoms,showModal:x.value,onModalClose:F},null,8,["product","uoms","showModal"])):c("",!0)]))}};export{ft as default};
